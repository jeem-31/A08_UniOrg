<?php

namespace App\Services\DocumentLibrary;

use DateTimeImmutable;
use Illuminate\Http\Request;

final class LibraryQueryParser
{
    public function parse(Request $request, AuthorizedFilterOptions $options): ParsedLibraryQuery
    {
        $input = $request->query->all();
        $rejected = [];
        $keyword = $this->keyword($input['q'] ?? null, $rejected);
        $filters = [];

        foreach (LibraryFilterCategory::cases() as $category) {
            $filters[$category->value] = $this->filterValues(
                $input[$category->value] ?? null,
                $category,
                $options,
                $rejected,
            );
        }

        $from = $this->dateBound($input['submitted_from'] ?? null, 'submitted_from', $rejected);
        $to = $this->dateBound($input['submitted_to'] ?? null, 'submitted_to', $rejected);

        if ($from !== null && $to !== null && $to < $from) {
            $to = null;
            $this->reject($rejected, 'submitted_to', 'The submission end date must be on or after the start date.');
        }

        $yearFrom = $this->yearBound($input['year_from'] ?? null, 'year_from', $options, $rejected);
        $archiveScope = $this->archiveScopeBound($input['archive_scope'] ?? null, 'archive_scope', $rejected);

        $sort = $this->sort($input['sort'] ?? null, $rejected);
        $page = $this->page($input['page'] ?? null, $rejected);

        $query = new LibraryQuery(
            keyword: $keyword,
            departments: $filters[LibraryFilterCategory::Department->value],
            requirements: $filters[LibraryFilterCategory::DocumentRequirement->value],
            documentTypes: $filters[LibraryFilterCategory::DocumentType->value],
            applicationKinds: $filters[LibraryFilterCategory::ApplicationKind->value],
            applicationStatuses: $filters[LibraryFilterCategory::ApplicationStatus->value],
            yearFrom: $yearFrom,
            submittedFrom: $from,
            submittedTo: $to,
            sort: $sort,
            page: $page,
            archiveScope: $archiveScope,
        );

        return new ParsedLibraryQuery(
            query: $query,
            rejectedInputs: $rejected,
            canonicalParameters: $this->canonicalParameters($query),
        );
    }

    /** @param array<string, list<string>> $rejected */
    private function keyword(mixed $value, array &$rejected): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value)) {
            $this->reject($rejected, 'q', 'The search keyword could not be used.');

            return null;
        }

        $trimmed = preg_replace('/^\s+|\s+$/u', '', $value);

        if ($trimmed === null) {
            $this->reject($rejected, 'q', 'The search keyword could not be used.');

            return null;
        }

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @param array<string, list<string>> $rejected
     * @return list<string>
     */
    private function filterValues(
        mixed $value,
        LibraryFilterCategory $category,
        AuthorizedFilterOptions $options,
        array &$rejected,
    ): array {
        if ($value === null || $value === []) {
            return [];
        }

        $submitted = is_array($value) ? $value : [$value];
        $accepted = [];

        foreach ($submitted as $candidate) {
            if (! is_string($candidate) || ! $options->contains($category, $candidate)) {
                $this->reject(
                    $rejected,
                    $category->value,
                    sprintf('A selected %s filter is unavailable.', $this->filterLabel($category)),
                );
                continue;
            }

            if (! in_array($candidate, $accepted, true)) {
                $accepted[] = $candidate;
            }
        }

        return $accepted;
    }

    /** @param array<string, list<string>> $rejected */
    private function dateBound(mixed $value, string $field, array &$rejected): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value) !== 1) {
            $this->reject($rejected, $field, 'Enter a valid submission date in YYYY-MM-DD format.');

            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();

        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            $this->reject($rejected, $field, 'Enter a valid submission date in YYYY-MM-DD format.');

            return null;
        }

        return $date;
    }

    /** @param array<string, list<string>> $rejected */
    private function yearBound(
        mixed $value,
        string $field,
        AuthorizedFilterOptions $options,
        array &$rejected,
    ): ?int {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value) || ! $options->contains(LibraryFilterCategory::AccreditationYear, $value)) {
            $this->reject($rejected, $field, 'Select an available accreditation year.');

            return null;
        }

        $year = filter_var($value, FILTER_VALIDATE_INT);

        return $year === false ? null : $year;
    }

    /** @param array<string, list<string>> $rejected */
    private function archiveScopeBound(mixed $value, string $field, array &$rejected): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_string($value) || ! in_array($value, ['current', 'archive', 'all'], true)) {
            $this->reject($rejected, $field, 'Select current, archive, or all for the archive scope.');
            return null;
        }
        return $value === 'all' ? null : $value;
    }

    /** @param array<string, list<string>> $rejected */
    private function sort(mixed $value, array &$rejected): LibrarySort
    {
        if ($value === null || $value === '') {
            return LibrarySort::default();
        }

        $sort = is_string($value) ? LibrarySort::tryFrom($value) : null;

        if ($sort === null) {
            $this->reject($rejected, 'sort', 'The selected sort option is unavailable.');

            return LibrarySort::default();
        }

        return $sort;
    }

    /** @param array<string, list<string>> $rejected */
    private function page(mixed $value, array &$rejected): int
    {
        if ($value === null || $value === '') {
            return 1;
        }

        if (! is_string($value) || preg_match('/^[1-9]\d*$/D', $value) !== 1) {
            $this->reject($rejected, 'page', 'The result page must be a positive whole number.');

            return 1;
        }

        $page = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($page === false) {
            $this->reject($rejected, 'page', 'The result page must be a positive whole number.');

            return 1;
        }

        return $page;
    }

    /** @return array<string, string|int|list<string>> */
    private function canonicalParameters(LibraryQuery $query): array
    {
        return $query->toCanonicalParameters();
    }

    private function filterLabel(LibraryFilterCategory $category): string
    {
        return match ($category) {
            LibraryFilterCategory::Department => 'department',
            LibraryFilterCategory::AccreditationYear => 'accreditation year',
            LibraryFilterCategory::DocumentRequirement => 'document requirement',
            LibraryFilterCategory::DocumentType => 'document type',
            LibraryFilterCategory::ApplicationKind => 'application kind',
            LibraryFilterCategory::ApplicationStatus => 'application status',
        };
    }



    /** @param array<string, list<string>> $rejected */
    private function reject(array &$rejected, string $field, string $message): void
    {
        $rejected[$field] ??= [];
        $rejected[$field][] = $message;
    }
}
