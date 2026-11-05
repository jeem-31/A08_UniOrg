<?php

namespace App\Services;

use Google\Cloud\Language\V1\Client\LanguageServiceClient;
use Google\Cloud\Language\V1\Document;
use Google\Cloud\Language\V1\Document\Type;
use Google\Cloud\Language\V1\EncodingType;
use Illuminate\Support\Facades\Log;

class GoogleCloudNLPService
{
    protected $client;
    protected $enabled;

    public function __construct()
    {
        $this->enabled = env('GOOGLE_CLOUD_NLP_ENABLED', false);
        
        if ($this->enabled) {
            try {
                // Set credentials path (absolute path)
                $keyFilePath = storage_path('app/google-cloud/credentials.json');
                
                if (!file_exists($keyFilePath)) {
                    Log::error('Google Cloud credentials file not found: ' . $keyFilePath);
                    $this->enabled = false;
                    return;
                }
                
                // Set environment variable
                putenv('GOOGLE_APPLICATION_CREDENTIALS=' . $keyFilePath);
                
                // Initialize client with explicit credentials
                // Create Guzzle client with SSL verification disabled (Windows development fix)
                $guzzleClient = new \GuzzleHttp\Client([
                    'verify' => false
                ]);
                
                $this->client = new LanguageServiceClient([
                    'credentials' => json_decode(file_get_contents($keyFilePath), true),
                    'transport' => 'rest',
                    'transportConfig' => [
                        'rest' => [
                            'httpHandler' => function ($request, $options = []) use ($guzzleClient) {
                                return $guzzleClient->sendAsync($request, $options);
                            }
                        ]
                    ]
                ]);
                
                Log::info('✅ Google Cloud NLP initialized successfully');
            } catch (\Exception $e) {
                Log::error('❌ Failed to initialize Google Cloud NLP: ' . $e->getMessage());
                $this->enabled = false;
            }
        }
    }

    /**
     * Check if Google Cloud NLP is enabled and available
     */
    public function isEnabled(): bool
    {
        return $this->enabled && $this->client !== null;
    }

    /**
     * Extract entities from text (names, organizations, locations, etc.)
     * 
     * @param string $text Text to analyze
     * @return array ['success' => bool, 'entities' => array]
     */
    public function extractEntities(string $text): array
    {
        if (!$this->isEnabled()) {
            return [
                'success' => false,
                'entities' => [],
                'error' => 'Google Cloud NLP is not enabled'
            ];
        }

        try {
            Log::info('🧠 [Google Cloud NLP] Extracting entities from text');

            // Create document
            $document = new Document();
            $document->setContent($text);
            $document->setType(Type::PLAIN_TEXT);

            // Create request object (required for v1.2+)
            $request = (new \Google\Cloud\Language\V1\AnalyzeEntitiesRequest())
                ->setDocument($document)
                ->setEncodingType(EncodingType::UTF8);

            // Analyze entities
            $response = $this->client->analyzeEntities($request);
            $entities = [];

            foreach ($response->getEntities() as $entity) {
                $entities[] = [
                    'name' => $entity->getName(),
                    'type' => $entity->getType(),
                    'salience' => $entity->getSalience(),
                    'mentions' => $entity->getMentions()->count()
                ];
            }

            // Track usage (1 unit per request)
            \App\Services\GoogleCloudUsageTracker::trackNLPUsage(1);

            Log::info('✅ [Google Cloud NLP] Entities extracted', [
                'count' => count($entities)
            ]);

            return [
                'success' => true,
                'entities' => $entities
            ];

        } catch (\Exception $e) {
            Log::error('❌ [Google Cloud NLP] Entity extraction failed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'entities' => [],
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Analyze sentiment of text
     * 
     * @param string $text Text to analyze
     * @return array ['success' => bool, 'sentiment' => array]
     */
    public function analyzeSentiment(string $text): array
    {
        if (!$this->isEnabled()) {
            return [
                'success' => false,
                'sentiment' => [],
                'error' => 'Google Cloud NLP is not enabled'
            ];
        }

        try {
            Log::info('😊 [Google Cloud NLP] Analyzing sentiment');

            // Create document
            $document = new Document();
            $document->setContent($text);
            $document->setType(Type::PLAIN_TEXT);

            // Create request object
            $request = (new \Google\Cloud\Language\V1\AnalyzeSentimentRequest())
                ->setDocument($document)
                ->setEncodingType(EncodingType::UTF8);

            // Analyze sentiment
            $response = $this->client->analyzeSentiment($request);
            $sentiment = $response->getDocumentSentiment();

            $result = [
                'score' => $sentiment->getScore(), // -1.0 (negative) to 1.0 (positive)
                'magnitude' => $sentiment->getMagnitude() // 0.0 to infinity (strength)
            ];

            Log::info('✅ [Google Cloud NLP] Sentiment analyzed', $result);

            return [
                'success' => true,
                'sentiment' => $result
            ];

        } catch (\Exception $e) {
            Log::error('❌ [Google Cloud NLP] Sentiment analysis failed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'sentiment' => [],
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Classify text into categories
     * 
     * @param string $text Text to classify
     * @return array ['success' => bool, 'categories' => array]
     */
    public function classifyText(string $text): array
    {
        if (!$this->isEnabled()) {
            return [
                'success' => false,
                'categories' => [],
                'error' => 'Google Cloud NLP is not enabled'
            ];
        }

        try {
            Log::info('🏷️ [Google Cloud NLP] Classifying text');

            // Create document
            $document = new Document();
            $document->setContent($text);
            $document->setType(Type::PLAIN_TEXT);

            // Create request object
            $request = (new \Google\Cloud\Language\V1\ClassifyTextRequest())
                ->setDocument($document);

            // Classify text
            $response = $this->client->classifyText($request);
            $categories = [];

            foreach ($response->getCategories() as $category) {
                $categories[] = [
                    'name' => $category->getName(),
                    'confidence' => $category->getConfidence()
                ];
            }

            Log::info('✅ [Google Cloud NLP] Text classified', [
                'categories_count' => count($categories)
            ]);

            return [
                'success' => true,
                'categories' => $categories
            ];

        } catch (\Exception $e) {
            Log::error('❌ [Google Cloud NLP] Text classification failed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'categories' => [],
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Analyze syntax (parts of speech, dependency tree)
     * 
     * @param string $text Text to analyze
     * @return array ['success' => bool, 'tokens' => array]
     */
    public function analyzeSyntax(string $text): array
    {
        if (!$this->isEnabled()) {
            return [
                'success' => false,
                'tokens' => [],
                'error' => 'Google Cloud NLP is not enabled'
            ];
        }

        try {
            Log::info('📝 [Google Cloud NLP] Analyzing syntax');

            // Create document
            $document = new Document();
            $document->setContent($text);
            $document->setType(Type::PLAIN_TEXT);

            // Create request object
            $request = (new \Google\Cloud\Language\V1\AnalyzeSyntaxRequest())
                ->setDocument($document)
                ->setEncodingType(EncodingType::UTF8);

            // Analyze syntax
            $response = $this->client->analyzeSyntax($request);
            
            $tokens = [];
            foreach ($response->getTokens() as $token) {
                $tokens[] = [
                    'text' => $token->getText()->getContent(),
                    'part_of_speech' => $token->getPartOfSpeech()->getTag(),
                    'lemma' => $token->getLemma()
                ];
            }

            Log::info('✅ [Google Cloud NLP] Syntax analyzed', [
                'tokens_count' => count($tokens)
            ]);

            return [
                'success' => true,
                'tokens' => $tokens
            ];

        } catch (\Exception $e) {
            Log::error('❌ [Google Cloud NLP] Syntax analysis failed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'tokens' => [],
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Close the client connection
     */
    public function __destruct()
    {
        if ($this->client) {
            $this->client->close();
        }
    }
}
