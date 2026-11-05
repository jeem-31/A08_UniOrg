<?php

namespace App\Services\DocumentLibrary;

final readonly class OrganizationFolderAggregate
{
    public function __construct(
        public OrganizationFolderIdentity $identity,
        public string $displayName,
        public ?string $acronym,
        public string $label,
        public string $token,
        public int $documentCount,
    ) {}
}