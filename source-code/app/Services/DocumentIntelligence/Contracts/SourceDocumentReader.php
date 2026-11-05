<?php

namespace App\Services\DocumentIntelligence\Contracts;

use App\Services\DocumentIntelligence\Data\ReadOnlyDocumentSource;
use App\Services\DocumentIntelligence\Exceptions\PermanentDocumentException;
use App\Services\DocumentIntelligence\Exceptions\TransientProcessingException;
use App\Services\DocumentIntelligence\StoredDocumentDescriptor;

interface SourceDocumentReader
{
    /** @throws PermanentDocumentException|TransientProcessingException */
    public function open(StoredDocumentDescriptor $source): ReadOnlyDocumentSource;
}
