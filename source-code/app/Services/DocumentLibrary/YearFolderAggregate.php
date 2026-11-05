<?php

namespace App\Services\DocumentLibrary;

final readonly class YearFolderAggregate
{
    public function __construct(
        public AccreditationYear|UnspecifiedYear $year,
        public int $documentCount,
    ) {}

    public function key(): string
    {
        return $this->year->key();
    }

    public function label(): string
    {
        return $this->year->label();
    }
}