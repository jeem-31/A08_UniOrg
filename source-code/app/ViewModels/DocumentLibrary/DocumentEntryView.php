<?php

namespace App\ViewModels\DocumentLibrary;

final readonly class DocumentEntryView
{
    /** @param list<string> $folderPath */
    public function __construct(
        public int $id,
        public string $originalFilename,
        public string $requirement,
        public string $organizationName,
        public string $accreditationYear,
        public ?string $submissionDate,
        public string $applicationKind,
        public string $applicationStatus,
        public string $processingStatus,
        public array $folderPath,
        public string $folderPathText,
        public ?string $openFolderUrl,
        public bool $detailsExpanded,
        public bool $detailsAvailable,
        public ?AdvisoryDetailsView $details,
        public bool $viewDocumentAvailable,
        public ?string $viewDocumentUrl,
        public bool $fullCaseAvailable,
        public ?string $fullCaseUrl,
        public bool $entryAvailable = true,
        public ?string $unavailableMessage = null,
        public ?string $viewDocumentUnavailableMessage = null,
        public ?string $fullCaseUnavailableMessage = null,
    ) {}
}
