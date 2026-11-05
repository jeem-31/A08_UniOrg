<?php

namespace App\Services\DocumentIntelligence;

final readonly class StoredDocumentDescriptor
{
    public function __construct(
        public int $applyOrgId,
        public string $field,
        public string $slot,
        public string $label,
        public string $originalFilename,
        public string $storageDisk,
        public string $storagePath,
        public string $sourceVersion,
        public string $sourceFingerprint,
    ) {}
}
