<?php

namespace App\Services\DocumentIntelligence;

use App\Services\DocumentIntelligence\Contracts\DocumentIntelligenceProvider;
use App\Services\DocumentIntelligence\Data\CandidateAIOutput;
use App\Services\DocumentIntelligence\Data\CandidateSummary;
use App\Services\DocumentIntelligence\Data\ExtractedDocumentText;
use App\Services\DocumentIntelligence\Exceptions\InvalidAIOutputException;
use App\Services\DocumentIntelligence\Exceptions\PermanentDocumentException;
use App\Services\DocumentIntelligence\Exceptions\TransientProcessingException;
use App\Services\DocumentIntelligence\Outcomes\InvalidAIOutputReason;
use App\Services\DocumentIntelligence\Outcomes\PermanentDocumentFailureReason;
use App\Services\DocumentIntelligence\Outcomes\TransientProcessingFailureReason;
use App\Services\GroqService;
use App\Services\GoogleCloudNLPService;
use Throwable;

final class GoogleDocumentIntelligenceProvider implements DocumentIntelligenceProvider
{
    public function __construct(
        private readonly GroqService $groq,
        private readonly GoogleCloudNLPService $nlp,
    ) {}

    public function analyze(ExtractedDocumentText $text): CandidateAIOutput
    {
        if (trim($text->text) === '') {
            throw new PermanentDocumentException(PermanentDocumentFailureReason::Empty);
        }

        $analysis = $this->requestAnalysis($text->text);
        $documentTypeName = trim((string) ($analysis['document_type'] ?? ''));
        $context = $this->contextFrom($analysis, $text->text);
        $tags = is_array($analysis['tags'] ?? null)
            ? array_values(array_filter($analysis['tags'], 'is_string'))
            : [];

        return new CandidateAIOutput(
            $text,
            $this->normalizeDocumentType($documentTypeName),
            $documentTypeName,
            trim((string) ($analysis['summary'] ?? '')),
            $context,
            $tags,
        );
    }

    public function summarize(ExtractedDocumentText $text): CandidateSummary
    {
        if (trim($text->text) === '') {
            throw new PermanentDocumentException(PermanentDocumentFailureReason::Empty);
        }

        $analysis = $this->requestAnalysis($text->text);

        return new CandidateSummary(trim((string) ($analysis['summary'] ?? '')));
    }

    private function requestAnalysis(string $text): array
    {
        try {
            if ($this->groq->isConfigured()) {
                return $this->groq->summarizeDocument($text);
            }

            // Missing API credentials is a configuration problem, not a
            // temporary provider outage. Surface an actionable message so the
            // reviewer is not told the service is "temporarily unavailable".
            throw new TransientProcessingException(
                TransientProcessingFailureReason::NotConfigured,
            );
        } catch (PermanentDocumentException|TransientProcessingException $failure) {
            throw $failure;
        } catch (InvalidAIOutputException $failure) {
            throw $failure;
        } catch (\UnexpectedValueException $failure) {
            // Empty or unparseable model output is an AI output problem, not an
            // availability problem. Surface an accurate, permanent reason so the
            // reviewer sees a truthful message instead of "service unavailable".
            throw new InvalidAIOutputException(
                InvalidAIOutputReason::MalformedOutput,
                previous: $failure,
            );
        } catch (Throwable $failure) {
            $message = strtolower($failure->getMessage());
            $reason = match (true) {
                preg_match('/429|413|rate\s?_?limit|quota/', $message) === 1 => TransientProcessingFailureReason::RateLimited,
                preg_match('/timeout|timed out|curl error 28/', $message) === 1 => TransientProcessingFailureReason::TimedOut,
                default => TransientProcessingFailureReason::ProviderUnavailable,
            };

            throw new TransientProcessingException($reason, previous: $failure);
        }
    }

    private function contextFrom(array $analysis, string $text): array
    {
        $context = [];
        foreach ([
            'organization_name' => 'organization',
            'school_year' => 'school_year',
            'key_dates' => 'dates',
            'key_information' => 'details',
        ] as $sourceKey => $contextKey) {
            $value = $analysis[$sourceKey] ?? null;
            if ($value !== null && $value !== '' && $value !== []) {
                $context[$contextKey] = $value;
            }
        }

        if ($this->nlp->isEnabled()) {
            $result = $this->nlp->extractEntities($text);
            if ($result['success'] ?? false) {
                foreach ($result['entities'] ?? [] as $entity) {
                    if (($entity['salience'] ?? 0) <= 0.08 || empty($entity['name'])) {
                        continue;
                    }

                    $key = match ((int) ($entity['type'] ?? 0)) {
                        1 => 'persons',
                        2 => 'locations',
                        3 => 'organizations',
                        11 => 'dates',
                        default => null,
                    };

                    if ($key !== null) {
                        $context['entities'][$key][] = (string) $entity['name'];
                    }
                }
            }
        }

        return $context;
    }

    private function normalizeDocumentType(string $type): string
    {
        $normalized = strtolower($type);
        $mapping = [
            'application for organization recognition' => 'application_form',
            'application for recognition' => 'application_form',
            'application for renewal' => 'application_form',
            'renewal of accredited' => 'application_form',
            'accredited student organization' => 'application_form',
            'recognition / renewal' => 'application_form',
            'letter of application' => 'letter_of_application',
            'adviser commitment' => 'adviser_commitment',
            'certification of officers' => 'certification_of_officers',
            'constitution' => 'constitution_bylaws',
            'plan of activities' => 'plan_of_activities',
            'program of activities' => 'plan_of_activities',
            'activity plan' => 'plan_of_activities',
            'certification of dean' => 'certification_dean',
            'dean certification' => 'certification_dean',
            'financial report' => 'financial_report',
            'accomplishment report' => 'accomplishment_reports',
            'list of members' => 'list_of_members',
            'membership list' => 'list_of_members',
            'accreditation form' => 'accreditation_form',
        ];

        foreach ($mapping as $label => $value) {
            if (str_contains($normalized, $label)) {
                return $value;
            }
        }

        return 'unknown';
    }
}
