<?php

namespace App\Services\DocumentLibrary;

final readonly class ParsedLibraryQuery
{
    /**
     * @param array<string, list<string>> $rejectedInputs
     * @param array<string, string|int|list<string>> $canonicalParameters
     */
    public function __construct(
        public LibraryQuery $query,
        public array $rejectedInputs,
        public array $canonicalParameters,
    ) {
    }

    public function hasRejectedInputs(): bool
    {
        return $this->rejectedInputs !== [];
    }

    /** @return list<string> */
    public function messagesFor(string $field): array
    {
        return $this->rejectedInputs[$field] ?? [];
    }
}
