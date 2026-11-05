<?php

namespace App\Services\DocumentLibrary;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class LibraryQuery
{
    public ?string $keyword;

    /** @var list<string> */
    public array $departments;

    /** @var list<string> */
    public array $requirements;

    /** @var list<string> */
    public array $documentTypes;

    /** @var list<string> */
    public array $applicationKinds;

    /** @var list<string> */
    public array $applicationStatuses;

    /**
     * @param  list<string>  $departments
     * @param  list<string>  $requirements
     * @param  list<string>  $documentTypes
     * @param  list<string>  $applicationKinds
     * @param  list<string>  $applicationStatuses
     */
    public ?string $archiveScope;

    public function __construct(
        ?string $keyword = null,
        array $departments = [],
        array $requirements = [],
        array $documentTypes = [],
        array $applicationKinds = [],
        array $applicationStatuses = [],
        public ?int $yearFrom = null,
        public ?DateTimeImmutable $submittedFrom = null,
        public ?DateTimeImmutable $submittedTo = null,
        public LibrarySort $sort = LibrarySort::NewestSubmission,
        public int $page = 1,
        ?string $archiveScope = null,
    ) {
        if ($page < 1) {
            throw new InvalidArgumentException('The library page must be positive.');
        }

        if ($submittedFrom !== null && $submittedTo !== null && $submittedTo < $submittedFrom) {
            throw new InvalidArgumentException('The submission end date cannot precede the start date.');
        }

        if ($archiveScope !== null && ! in_array($archiveScope, ['current', 'archive', 'all'], true)) {
            throw new InvalidArgumentException('The archive scope must be current, archive, or all.');
        }

        $this->archiveScope = $archiveScope === 'all' ? null : $archiveScope;
        $this->keyword = self::normalizeKeyword($keyword);
        $this->departments = self::normalizeValues($departments);
        $this->requirements = self::normalizeValues($requirements);
        $this->documentTypes = self::normalizeValues($documentTypes);
        $this->applicationKinds = self::normalizeValues($applicationKinds);
        $this->applicationStatuses = self::normalizeValues($applicationStatuses);
    }

    public function withKeyword(?string $keyword): self
    {
        return $this->copy(['keyword' => $keyword, 'page' => 1]);
    }

    public function withoutKeyword(): self
    {
        return $this->copy(['keyword' => null, 'page' => 1]);
    }

    public function withPage(int $page): self
    {
        return $this->copy(['page' => $page]);
    }

    public function withSort(LibrarySort $sort): self
    {
        return $this->copy(['sort' => $sort, 'page' => 1]);
    }

    /** @param list<string> $values */
    public function withFilter(LibraryFilterCategory $category, array $values): self
    {
        $filters = $this->filters();
        $filters[$category->value] = $values;

        return $this->copy(['filters' => $filters, 'page' => 1]);
    }

    public function withAddedFilterValue(LibraryFilterCategory $category, string $value): self
    {
        $values = $this->filter($category);
        $values[] = $value;

        return $this->withFilter($category, $values);
    }

    public function withoutFilterValue(LibraryFilterCategory $category, string $value): self
    {
        return $this->withFilter(
            $category,
            array_values(array_filter(
                $this->filter($category),
                static fn (string $selected): bool => $selected !== $value,
            )),
        );
    }

    public function withSubmissionDateRange(
        ?DateTimeImmutable $submittedFrom,
        ?DateTimeImmutable $submittedTo,
    ): self {
        return $this->copy([
            'submittedFrom' => $submittedFrom,
            'submittedTo' => $submittedTo,
            'page' => 1,
        ]);
    }

    public function withYear(?int $year): self
    {
        return $this->copy([
            'yearFrom' => $year,
            'page' => 1,
        ]);
    }

    public function withArchiveScope(?string $scope): self
    {
        return $this->copy([
            'archiveScope' => $scope,
            'page' => 1,
        ]);
    }

    public function clearAll(): self
    {
        return new self(sort: $this->sort);
    }

    /** @return list<string> */
    public function filter(LibraryFilterCategory $category): array
    {
        return $this->filters()[$category->value];
    }

    /** @return array<string, list<string>> */
    public function filters(): array
    {
        return [
            LibraryFilterCategory::Department->value => $this->departments,
            LibraryFilterCategory::DocumentRequirement->value => $this->requirements,
            LibraryFilterCategory::DocumentType->value => $this->documentTypes,
            LibraryFilterCategory::ApplicationKind->value => $this->applicationKinds,
            LibraryFilterCategory::ApplicationStatus->value => $this->applicationStatuses,
        ];
    }

    public function hasFilters(): bool
    {
        return $this->yearFrom !== null
            || $this->archiveScope !== null
            || $this->submittedFrom !== null
            || $this->submittedTo !== null
            || array_any($this->filters(), static fn (array $values): bool => $values !== []);
    }

    /** @return array<string, string|int|list<string>> */
    public function toCanonicalParameters(): array
    {
        $parameters = $this->keyword === null ? [] : ['q' => $this->keyword];

        foreach ($this->filters() as $field => $values) {
            if ($values !== []) {
                $parameters[$field] = $values;
            }
        }

        if ($this->yearFrom !== null) {
            $parameters['year_from'] = (string) $this->yearFrom;
        }
        if ($this->archiveScope !== null) {
            $parameters['archive_scope'] = $this->archiveScope;
        }
        if ($this->submittedFrom !== null) {
            $parameters['submitted_from'] = $this->submittedFrom->format('Y-m-d');
        }
        if ($this->submittedTo !== null) {
            $parameters['submitted_to'] = $this->submittedTo->format('Y-m-d');
        }
        if ($this->sort !== LibrarySort::default()) {
            $parameters['sort'] = $this->sort->value;
        }
        if ($this->page !== 1) {
            $parameters['page'] = $this->page;
        }

        return $parameters;
    }

    /** @param array<string, mixed> $changes */
    private function copy(array $changes): self
    {
        /** @var array<string, list<string>> $filters */
        $filters = $changes['filters'] ?? $this->filters();

        return new self(
            keyword: array_key_exists('keyword', $changes) ? $changes['keyword'] : $this->keyword,
            departments: $filters[LibraryFilterCategory::Department->value],
            requirements: $filters[LibraryFilterCategory::DocumentRequirement->value],
            documentTypes: $filters[LibraryFilterCategory::DocumentType->value],
            applicationKinds: $filters[LibraryFilterCategory::ApplicationKind->value],
            applicationStatuses: $filters[LibraryFilterCategory::ApplicationStatus->value],
            yearFrom: array_key_exists('yearFrom', $changes) ? $changes['yearFrom'] : $this->yearFrom,
            submittedFrom: array_key_exists('submittedFrom', $changes) ? $changes['submittedFrom'] : $this->submittedFrom,
            submittedTo: array_key_exists('submittedTo', $changes) ? $changes['submittedTo'] : $this->submittedTo,
            sort: $changes['sort'] ?? $this->sort,
            page: $changes['page'] ?? $this->page,
            archiveScope: array_key_exists('archiveScope', $changes) ? $changes['archiveScope'] : $this->archiveScope,
        );
    }

    private static function normalizeKeyword(?string $keyword): ?string
    {
        if ($keyword === null) {
            return null;
        }

        $keyword = preg_replace('/^\s+|\s+$/u', '', $keyword);

        if ($keyword === null) {
            throw new InvalidArgumentException('The library keyword must be valid UTF-8.');
        }

        return $keyword === '' ? null : $keyword;
    }

    /** @param array<array-key, mixed> $values
     * @return list<string>
     */
    private static function normalizeValues(array $values): array
    {
        $normalized = [];

        foreach ($values as $value) {
            if (! is_string($value) || $value === '') {
                throw new InvalidArgumentException('Library filter values must be non-empty strings.');
            }

            if (! in_array($value, $normalized, true)) {
                $normalized[] = $value;
            }
        }

        return $normalized;
    }
}
