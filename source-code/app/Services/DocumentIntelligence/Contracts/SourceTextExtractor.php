<?php

namespace App\Services\DocumentIntelligence\Contracts;

use App\Services\DocumentIntelligence\Data\ExtractedDocumentText;
use App\Services\DocumentIntelligence\Data\ReadOnlyDocumentSource;
use App\Services\DocumentIntelligence\Exceptions\PermanentDocumentException;
use App\Services\DocumentIntelligence\Exceptions\TransientProcessingException;

interface SourceTextExtractor
{
    /** @throws PermanentDocumentException|TransientProcessingException */
    public function extract(ReadOnlyDocumentSource $source): ExtractedDocumentText;
}
