<?php

namespace App\Services\DocumentIntelligence\Outcomes;

enum InvalidAIOutputReason: string
{
    case EmptyExtractedText = 'empty_extracted_text';
    case MissingDocumentType = 'missing_document_type';
    case EmptySummary = 'empty_summary';
    case InvalidTagCount = 'invalid_tag_count';
    case MalformedOutput = 'malformed_output';
}
