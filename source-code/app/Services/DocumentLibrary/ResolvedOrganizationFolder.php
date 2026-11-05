<?php

namespace App\Services\DocumentLibrary;

final readonly class ResolvedOrganizationFolder
{
    /** @param list<int> $applicationIds */
    public function __construct(
        public OrganizationFolderIdentity $identity,
        public string $label,
        public array $applicationIds,
        public string $token,
    ) {}
}
