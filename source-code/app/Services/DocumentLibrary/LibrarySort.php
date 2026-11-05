<?php

namespace App\Services\DocumentLibrary;

enum LibrarySort: string
{
    case NewestSubmission = 'newest';
    case OldestSubmission = 'oldest';
    case FilenameAscending = 'filename_asc';
    case FilenameDescending = 'filename_desc';

    public static function default(): self
    {
        return self::NewestSubmission;
    }

    public function label(): string
    {
        return match ($this) {
            self::NewestSubmission => 'Newest submission',
            self::OldestSubmission => 'Oldest submission',
            self::FilenameAscending => 'Filename A–Z',
            self::FilenameDescending => 'Filename Z–A',
        };
    }

    /**
     * @return list<array{field: 'submission_date'|'filename'|'entry_id', direction: 'asc'|'desc', case_insensitive: bool}>
     */
    public function orderDefinition(): array
    {
        $primary = match ($this) {
            self::NewestSubmission => ['field' => 'submission_date', 'direction' => 'desc', 'case_insensitive' => false],
            self::OldestSubmission => ['field' => 'submission_date', 'direction' => 'asc', 'case_insensitive' => false],
            self::FilenameAscending => ['field' => 'filename', 'direction' => 'asc', 'case_insensitive' => true],
            self::FilenameDescending => ['field' => 'filename', 'direction' => 'desc', 'case_insensitive' => true],
        };

        return [$primary, ['field' => 'entry_id', 'direction' => 'desc', 'case_insensitive' => false]];
    }
}
