<?php

namespace App\Services;

use RuntimeException;
use Smalot\PdfParser\Parser;
use Smalot\PdfParser\PDFObject;

/**
 * Reads the embedded, page-local content of a Certification of Officers PDF.
 *
 * This service deliberately has no application, network, rendering, or image
 * dependencies. It leaves fixed-layout validation and record parsing to the
 * dedicated parser introduced separately.
 */
final class CertificationOfOfficersPdfExtractor
{
    /** @var array<string, list<string>> */
    private const FIELD_ALIASES = [
        'student_name' => ['studentname', 'nameofstudent', 'officername'],
        'year_section' => ['yearsection', 'yearandsection', 'yearsec'],
        'position' => ['position', 'officerposition', 'officerrole'],
    ];

    public function __construct(private readonly Parser $parser = new Parser) {}

    /**
     * @return list<array{
     *     index: int,
     *     text: string,
     *     form_fields: array<string, string>,
     *     preferred_values: array<string, string>
     * }>
     */
    public function extract(string $temporaryPdfPath): array
    {
        if (! is_file($temporaryPdfPath) || ! is_readable($temporaryPdfPath)) {
            throw new RuntimeException('The local Certification of Officers PDF could not be read.');
        }

        $bytes = file_get_contents($temporaryPdfPath);
        if ($bytes === false) {
            throw new RuntimeException('The local Certification of Officers PDF could not be read.');
        }

        $pages = $this->extractBytes($bytes);

        // If any extracted page is empty (scanned PDF without vector text layer), run OCR fallback
        $hasEmptyPage = false;
        foreach ($pages as $p) {
            if (empty(trim($p['text']))) {
                $hasEmptyPage = true;
                break;
            }
        }

        if ($hasEmptyPage && class_exists(\App\Services\OcrService::class)) {
            try {
                $ocr = app(\App\Services\OcrService::class);
                $ocrText = $ocr->basahinAngPictureVisual($temporaryPdfPath, count($pages) ?: 16);
                if (! empty(trim($ocrText))) {
                    $ocrPageTexts = preg_split('/--- PAGE BREAK ---/i', $ocrText);
                    foreach ($pages as $index => &$pageRef) {
                        if (empty(trim($pageRef['text'])) && isset($ocrPageTexts[$index])) {
                            $pageRef['text'] = trim($ocrPageTexts[$index]);
                        }
                    }
                    unset($pageRef);
                }
            } catch (\Throwable $e) {
                // Fallback silently if OCR is unavailable
            }
        }

        return $pages;
    }

    /**
     * @return list<array{
     *     index: int,
     *     text: string,
     *     form_fields: array<string, string>,
     *     preferred_values: array<string, string>
     * }>
     */
    public function extractBytes(string $localPdfBytes): array
    {
        try {
            $document = $this->parser->parseContent($localPdfBytes);
            $pages = $document->getPages();
        } catch (\Throwable $exception) {
            throw new RuntimeException(
                'The local Certification of Officers PDF could not be read.',
                previous: $exception,
            );
        }

        $pageIndexes = [];
        foreach ($pages as $index => $page) {
            $pageIndexes[spl_object_id($page)] = $index;
        }

        $formFieldsByPage = array_fill(0, count($pages), []);
        foreach ($document->getObjects() as $object) {
            if (! $this->isWidget($object)) {
                continue;
            }

            $pageIndex = $this->findPageIndex($object, $pageIndexes);
            $fieldName = $this->fieldName($object);
            $value = $this->fieldValue($object);
            if ($pageIndex === null || $fieldName === '' || $value === '') {
                continue;
            }

            // The first field in object order wins for duplicate field names.
            $formFieldsByPage[$pageIndex] ??= [];
            $formFieldsByPage[$pageIndex][$fieldName] ??= $value;
        }

        $extractedPages = [];
        foreach ($pages as $index => $page) {
            $formFields = $formFieldsByPage[$index] ?? [];
            $extractedPages[] = [
                'index' => $index,
                'text' => trim($page->getText()),
                'form_fields' => $formFields,
                'preferred_values' => $this->recognizedValues($formFields),
            ];
        }

        return $extractedPages;
    }

    private function isWidget(PDFObject $object): bool
    {
        return strtoupper($this->elementText($object->get('Subtype'))) === 'WIDGET';
    }

    /** @param array<int, int> $pageIndexes */
    private function findPageIndex(PDFObject $field, array $pageIndexes): ?int
    {
        foreach ($this->fieldLineage($field) as $entry) {
            $page = $this->asObject($entry->get('P'));
            if ($page !== null && array_key_exists(spl_object_id($page), $pageIndexes)) {
                return $pageIndexes[spl_object_id($page)];
            }
        }

        return null;
    }

    private function fieldName(PDFObject $field): string
    {
        $parts = [];
        foreach ($this->fieldLineage($field) as $entry) {
            $part = $this->normalizeWhitespace($this->elementText($entry->get('T')));
            if ($part !== '') {
                array_unshift($parts, $part);
            }
        }

        return implode('.', $parts);
    }

    private function fieldValue(PDFObject $field): string
    {
        foreach ($this->fieldLineage($field) as $entry) {
            $value = $this->normalizeWhitespace($this->elementText($entry->get('V')));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /** @return list<PDFObject> */
    private function fieldLineage(PDFObject $field): array
    {
        $lineage = [];
        $seen = [];
        $current = $field;

        while ($current !== null && ! isset($seen[spl_object_id($current)])) {
            $lineage[] = $current;
            $seen[spl_object_id($current)] = true;
            $current = $this->asObject($current->get('Parent'));
        }

        return $lineage;
    }

    /** @param array<string, string> $formFields */
    private function recognizedValues(array $formFields): array
    {
        $recognized = [];
        foreach ($formFields as $fieldName => $value) {
            $canonical = $this->canonicalFieldName($fieldName);
            if ($canonical !== null && $value !== '' && ! isset($recognized[$canonical])) {
                $recognized[$canonical] = $value;
            }
        }

        return $recognized;
    }

    private function canonicalFieldName(string $fieldName): ?string
    {
        $normalized = strtolower((string) preg_replace('/[^a-z0-9]+/', '', $fieldName));
        foreach (self::FIELD_ALIASES as $canonical => $aliases) {
            if (in_array($normalized, $aliases, true)) {
                return $canonical;
            }
        }

        return null;
    }

    private function asObject(mixed $value): ?PDFObject
    {
        return $value instanceof PDFObject ? $value : null;
    }

    private function elementText(mixed $element): string
    {
        if (is_object($element) && method_exists($element, 'getContent')) {
            $content = $element->getContent();
            if (is_string($content) || is_numeric($content)) {
                return (string) $content;
            }
        }

        return is_scalar($element) ? (string) $element : '';
    }

    private function normalizeWhitespace(string $value): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $value));
    }
}
