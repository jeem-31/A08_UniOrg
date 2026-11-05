<?php

namespace App\Services\DocumentIntelligence\Contracts;

use App\Services\DocumentIntelligence\Data\CandidateAIOutput;
use App\Services\DocumentIntelligence\Data\CandidateSummary;
use App\Services\DocumentIntelligence\Data\ExtractedDocumentText;
use App\Services\DocumentIntelligence\Exceptions\PermanentDocumentException;
use App\Services\DocumentIntelligence\Exceptions\TransientProcessingException;

interface DocumentIntelligenceProvider
{
    /** @throws PermanentDocumentException|TransientProcessingException */
    public function analyze(ExtractedDocumentText $text): CandidateAIOutput;

    /** @throws PermanentDocumentException|TransientProcessingException */
    public function summarize(ExtractedDocumentText $text): CandidateSummary;
}
