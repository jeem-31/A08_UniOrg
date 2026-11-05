<?php

namespace App\Services\DocumentIntelligence\Outcomes;

enum PermanentDocumentFailureReason: string
{
    case Missing = 'document_missing';
    case Unreadable = 'document_unreadable';
    case Empty = 'document_empty';
    case Unsupported = 'document_unsupported';
}
