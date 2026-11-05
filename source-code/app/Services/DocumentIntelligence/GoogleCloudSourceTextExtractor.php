<?php

namespace App\Services\DocumentIntelligence;

use App\Services\DocumentIntelligence\Contracts\SourceTextExtractor;
use App\Services\DocumentIntelligence\Data\ExtractedDocumentText;
use App\Services\DocumentIntelligence\Data\ReadOnlyDocumentSource;
use App\Services\DocumentIntelligence\Exceptions\PermanentDocumentException;
use App\Services\DocumentIntelligence\Exceptions\TransientProcessingException;
use App\Services\DocumentIntelligence\Outcomes\PermanentDocumentFailureReason;
use App\Services\DocumentIntelligence\Outcomes\TransientProcessingFailureReason;
use App\Services\GoogleCloudVisionService;
use App\Services\OcrService;
use Throwable;

final class GoogleCloudSourceTextExtractor implements SourceTextExtractor
{
    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png'];

    public function __construct(
        private readonly GoogleCloudVisionService $vision,
        private readonly OcrService $localOcr,
    ) {}

    public function extract(ReadOnlyDocumentSource $source): ExtractedDocumentText
    {
        $extension = strtolower(pathinfo($source->descriptor()->originalFilename, PATHINFO_EXTENSION)
            ?: pathinfo($source->descriptor()->storagePath, PATHINFO_EXTENSION));

        if (! in_array($extension, ['pdf', 'docx', ...self::IMAGE_EXTENSIONS], true)) {
            throw new PermanentDocumentException(PermanentDocumentFailureReason::Unsupported);
        }

        $ownedCopy = null;
        try {
            $path = $source instanceof LocalTemporaryDocumentSource
                ? $source->localPath()
                : ($ownedCopy = $this->copyToLocalPath($source, $extension));

            if (! is_file($path) || ! is_readable($path)) {
                throw new PermanentDocumentException(PermanentDocumentFailureReason::Unreadable);
            }

            [$text, $method] = $this->extractFromPath($path, $extension);
            $text = trim($text);

            if ($text === '') {
                throw new PermanentDocumentException(PermanentDocumentFailureReason::Empty);
            }

            return new ExtractedDocumentText($text, $this->mimeType($extension), $method);
        } catch (PermanentDocumentException|TransientProcessingException $failure) {
            throw $failure;
        } catch (Throwable $failure) {
            throw $this->classifyFailure($failure);
        } finally {
            if ($ownedCopy !== null && is_file($ownedCopy)) {
                @unlink($ownedCopy);
            }
        }
    }

    private function extractFromPath(string $path, string $extension): array
    {
        if ($extension === 'docx' || ! $this->vision->isEnabled()) {
            return [$this->localOcr->basahinAngPicture($path, 1), 'local_document_extraction'];
        }

        $result = $extension === 'pdf'
            ? $this->vision->extractTextFromPdf($path, 1)
            : $this->vision->extractText($path);

        if (! ($result['success'] ?? false)) {
            $message = (string) ($result['error'] ?? 'Document extraction failed.');
            if (preg_match('/no text|empty/i', $message) === 1) {
                throw new PermanentDocumentException(PermanentDocumentFailureReason::Empty);
            }

            throw $this->classifyFailure(new \RuntimeException($message));
        }

        return [(string) ($result['text'] ?? ''), (string) ($result['method'] ?? 'google_cloud_vision')];
    }

    private function copyToLocalPath(ReadOnlyDocumentSource $source, string $extension): string
    {
        $path = tempnam(sys_get_temp_dir(), 'document_');
        if ($path === false) {
            throw new TransientProcessingException(TransientProcessingFailureReason::ProviderUnavailable);
        }

        $target = $path.'.'.$extension;
        @rename($path, $target);
        $input = $source->readStream();
        $output = @fopen($target, 'wb');

        if ($output === false || stream_copy_to_stream($input, $output) === false) {
            is_resource($input) && fclose($input);
            is_resource($output) && fclose($output);
            @unlink($target);
            throw new PermanentDocumentException(PermanentDocumentFailureReason::Unreadable);
        }

        fclose($input);
        fclose($output);

        return $target;
    }

    private function classifyFailure(Throwable $failure): TransientProcessingException|PermanentDocumentException
    {
        $message = strtolower($failure->getMessage());

        if (preg_match('/timeout|timed out|curl error 28/', $message) === 1) {
            return new TransientProcessingException(TransientProcessingFailureReason::TimedOut, previous: $failure);
        }

        if (preg_match('/429|rate limit|quota/', $message) === 1) {
            return new TransientProcessingException(TransientProcessingFailureReason::RateLimited, previous: $failure);
        }

        if (preg_match('/not enabled|credentials|connection|service unavailable|503|ghostscript|tesseract/', $message) === 1) {
            return new TransientProcessingException(TransientProcessingFailureReason::ProviderUnavailable, previous: $failure);
        }

        return new PermanentDocumentException(PermanentDocumentFailureReason::Unreadable, previous: $failure);
    }

    private function mimeType(string $extension): string
    {
        return match ($extension) {
            'pdf' => 'application/pdf',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'png' => 'image/png',
            default => 'image/jpeg',
        };
    }
}
