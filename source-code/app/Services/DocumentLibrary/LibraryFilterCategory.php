<?php

namespace App\Services\DocumentLibrary;

enum LibraryFilterCategory: string
{
    case Department = 'department';
    case AccreditationYear = 'year';
    case DocumentRequirement = 'requirement';
    case DocumentType = 'document_type';
    case ApplicationKind = 'application_kind';
    case ApplicationStatus = 'application_status';
}
