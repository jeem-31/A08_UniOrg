<?php

namespace App\Console\Commands;

use App\Models\ApplyOrg;
use App\Services\DocumentLibrary\DocumentLibrarySourceIndexer;
use Illuminate\Console\Command;

final class BackfillDocumentLibraryEntries extends Command
{
    private const CHUNK_SIZE = 200;

    protected $signature = 'document-library:backfill';

    protected $description = 'Create or repair submitted-document library entries from current application sources';

    public function handle(DocumentLibrarySourceIndexer $indexer): int
    {
        $applicationCount = 0;
        $documentCount = 0;

        ApplyOrg::query()
            ->chunkById(self::CHUNK_SIZE, function ($applications) use (
                $indexer,
                &$applicationCount,
                &$documentCount,
            ): void {
                foreach ($applications as $application) {
                    $documentCount += $indexer->syncApplication($application);
                    $applicationCount++;
                }
            });

        $this->info("Indexed {$documentCount} document(s) from {$applicationCount} application(s).");

        return self::SUCCESS;
    }
}
