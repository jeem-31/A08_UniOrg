<?php

namespace App\Services\DocumentIntelligence;

use App\Services\DocumentIntelligence\Data\ReadOnlyDocumentSource;
use RuntimeException;

final class LocalTemporaryDocumentSource implements ReadOnlyDocumentSource
{
    public function __construct(
        private readonly StoredDocumentDescriptor $sourceDescriptor,
        private readonly string $temporaryPath,
    ) {}

    public function descriptor(): StoredDocumentDescriptor
    {
        return $this->sourceDescriptor;
    }

    public function localPath(): string
    {
        return $this->temporaryPath;
    }

    public function readStream()
    {
        $stream = @fopen($this->temporaryPath, 'rb');

        if ($stream === false) {
            throw new RuntimeException('The temporary document copy is not readable.');
        }

        return $stream;
    }

    public function cleanup(): void
    {
        if (is_file($this->temporaryPath)) {
            @chmod($this->temporaryPath, 0600);
            @unlink($this->temporaryPath);
        }
    }

    public function __destruct()
    {
        $this->cleanup();
    }
}
