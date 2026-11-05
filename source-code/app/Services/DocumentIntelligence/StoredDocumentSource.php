<?php

namespace App\Services\DocumentIntelligence;

use LogicException;

final readonly class StoredDocumentSource
{
    public function __construct(
        public string $field,
        public string $slot,
        public string $label,
        public string $originalFilename,
        public string $storageDisk,
        public string $storagePath,
        public ?string $sourceVersion = null,
        public ?string $sourceFingerprint = null,
    ) {}

    public function hasSourceIdentity(): bool
    {
        return $this->sourceVersion !== null && $this->sourceFingerprint !== null;
    }

    public function descriptorFor(int $applyOrgId): StoredDocumentDescriptor
    {
        if (! $this->hasSourceIdentity()) {
            throw new LogicException('A source version and fingerprint are required to create a descriptor.');
        }

        return new StoredDocumentDescriptor(
            $applyOrgId,
            $this->field,
            $this->slot,
            $this->label,
            $this->originalFilename,
            $this->storageDisk,
            $this->storagePath,
            $this->sourceVersion,
            $this->sourceFingerprint,
        );
    }
}
