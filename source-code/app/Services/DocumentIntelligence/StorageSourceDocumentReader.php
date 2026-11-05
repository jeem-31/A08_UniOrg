<?php

namespace App\Services\DocumentIntelligence;

use App\Services\DocumentIntelligence\Contracts\SourceDocumentReader;
use App\Services\DocumentIntelligence\Data\ReadOnlyDocumentSource;
use App\Services\DocumentIntelligence\Exceptions\PermanentDocumentException;
use App\Services\DocumentIntelligence\Exceptions\TransientProcessingException;
use App\Services\DocumentIntelligence\Outcomes\PermanentDocumentFailureReason;
use App\Services\DocumentIntelligence\Outcomes\TransientProcessingFailureReason;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class StorageSourceDocumentReader implements SourceDocumentReader
{
    private const SUPPORTED_EXTENSIONS = ['pdf', 'docx', 'jpg', 'jpeg', 'png'];

    public function open(StoredDocumentDescriptor $source): ReadOnlyDocumentSource
    {
        $extension = strtolower(pathinfo($source->originalFilename, PATHINFO_EXTENSION)
            ?: pathinfo($source->storagePath, PATHINFO_EXTENSION));

        if (! in_array($extension, self::SUPPORTED_EXTENSIONS, true)) {
            throw new PermanentDocumentException(PermanentDocumentFailureReason::Unsupported);
        }

        $temporaryPath = null;

        try {
            $disk = Storage::disk($source->storageDisk);

            if (! $disk->exists($source->storagePath)) {
                throw new PermanentDocumentException(PermanentDocumentFailureReason::Missing);
            }

            $input = $disk->readStream($source->storagePath);
            if (! is_resource($input)) {
                throw new PermanentDocumentException(PermanentDocumentFailureReason::Unreadable);
            }

            $directory = storage_path('app/document-intelligence');
            if (! is_dir($directory) && ! @mkdir($directory, 0700, true) && ! is_dir($directory)) {
                fclose($input);
                throw new TransientProcessingException(TransientProcessingFailureReason::ProviderUnavailable);
            }

            $basePath = tempnam($directory, 'source_');
            if ($basePath === false) {
                fclose($input);
                throw new TransientProcessingException(TransientProcessingFailureReason::ProviderUnavailable);
            }

            $temporaryPath = $basePath.'.'.$extension;
            if (! @rename($basePath, $temporaryPath)) {
                @unlink($basePath);
                fclose($input);
                throw new TransientProcessingException(TransientProcessingFailureReason::ProviderUnavailable);
            }

            $output = @fopen($temporaryPath, 'wb');
            if ($output === false) {
                fclose($input);
                throw new TransientProcessingException(TransientProcessingFailureReason::ProviderUnavailable);
            }

            $bytesCopied = stream_copy_to_stream($input, $output);
            fclose($input);
            fclose($output);

            if ($bytesCopied === false) {
                throw new TransientProcessingException(TransientProcessingFailureReason::ProviderUnavailable);
            }

            if ($bytesCopied === 0) {
                throw new PermanentDocumentException(PermanentDocumentFailureReason::Empty);
            }

            @chmod($temporaryPath, 0444);

            return new LocalTemporaryDocumentSource($source, $temporaryPath);
        } catch (PermanentDocumentException|TransientProcessingException $failure) {
            if ($temporaryPath !== null && is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }

            throw $failure;
        } catch (Throwable $failure) {
            if ($temporaryPath !== null && is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }

            throw new TransientProcessingException(
                TransientProcessingFailureReason::ProviderUnavailable,
                previous: $failure,
            );
        }
    }
}
