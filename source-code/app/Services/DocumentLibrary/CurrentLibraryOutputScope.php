<?php

namespace App\Services\DocumentLibrary;

use App\Models\ProcessedDocument;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use InvalidArgumentException;

final class CurrentLibraryOutputScope
{
    private const ANALYSIS_ALIAS = 'library_analysis';

    /** @var list<string> */
    private const OUTPUT_COLUMNS = [
        'document_type',
        'document_type_name',
        'summary',
        'tags',
        'key_information',
        'extracted_text',
        'confidence_score',
    ];

    /**
     * Join the analysis for the entry's current source and project generated output safely.
     *
     * The logical analysis key is unique in the lifecycle table. Non-completed current
     * analyses remain joined for their status, while every generated value is null unless
     * the completed output and current entry share the same non-null source version.
     */
    public function apply(Builder $query, string $entryAlias = 'document_library_entries'): Builder
    {
        $this->assertIdentifier($entryAlias);

        $analysis = self::ANALYSIS_ALIAS;
        $normalizedEntryPath = $this->normalizedPath($query, "{$entryAlias}.file_path");
        $normalizedAnalysisPath = $this->normalizedPath($query, "{$analysis}.file_path");

        $query->select("{$entryAlias}.*")
            ->leftJoin("processed_documents as {$analysis}", function (JoinClause $join) use (
                $entryAlias,
                $analysis,
                $normalizedEntryPath,
                $normalizedAnalysisPath,
            ): void {
                $join->on("{$analysis}.apply_org_id", '=', "{$entryAlias}.apply_org_id")
                    ->on("{$analysis}.document_field", '=', "{$entryAlias}.document_field")
                    ->on("{$analysis}.document_slot", '=', "{$entryAlias}.document_slot")
                    ->on("{$analysis}.storage_disk", '=', "{$entryAlias}.storage_disk")
                    ->whereNotNull("{$analysis}.association_ready_at")
                    ->whereRaw("{$normalizedAnalysisPath} = {$normalizedEntryPath}");

                $join->whereRaw(
                    "({$entryAlias}.source_version IS NULL OR "
                    ."{$analysis}.source_version = {$entryAlias}.source_version)"
                )->whereRaw(
                    "({$entryAlias}.source_fingerprint IS NULL OR "
                    ."LOWER({$analysis}.source_fingerprint) = LOWER({$entryAlias}.source_fingerprint))"
                );
            });

        $currentOutput = implode(' AND ', [
            "{$analysis}.processing_status = ?",
            "{$entryAlias}.source_version IS NOT NULL",
            "{$analysis}.source_version IS NOT NULL",
            "{$analysis}.output_source_version IS NOT NULL",
            "{$analysis}.source_version = {$entryAlias}.source_version",
            "{$analysis}.output_source_version = {$analysis}.source_version",
        ]);

        $query->addSelect([
            "{$analysis}.id as analysis_id",
            "{$analysis}.processing_status as processing_status",
        ])->selectRaw(
            "CASE WHEN {$currentOutput} THEN 1 ELSE 0 END AS has_current_output",
            [ProcessedDocument::STATUS_COMPLETED],
        )->selectRaw(
            "CASE WHEN {$currentOutput} THEN {$analysis}.processed_at ELSE NULL END AS processed_at",
            [ProcessedDocument::STATUS_COMPLETED],
        );

        foreach (self::OUTPUT_COLUMNS as $column) {
            $query->selectRaw(
                "CASE WHEN {$currentOutput} THEN {$analysis}.{$column} ELSE NULL END AS {$column}",
                [ProcessedDocument::STATUS_COMPLETED],
            );
        }

        return $query;
    }

    private function normalizedPath(Builder $query, string $column): string
    {
        return match ($query->getConnection()->getDriverName()) {
            'pgsql' => "ltrim(replace(btrim({$column}), chr(92), '/'), '/')",
            'mysql' => "trim(leading '/' from replace(trim({$column}), char(92), '/'))",
            default => "ltrim(replace(trim({$column}), char(92), '/'), '/')",
        };
    }

    private function assertIdentifier(string $identifier): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $identifier) !== 1) {
            throw new InvalidArgumentException('The document library entry alias is invalid.');
        }
    }
}
