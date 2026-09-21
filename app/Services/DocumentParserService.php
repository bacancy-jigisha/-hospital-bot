<?php

namespace App\Services;

use App\Exceptions\DocumentProcessingException;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser;

/**
 * Pulls the plain text a PDF already carries in its text layer.
 *
 * "Parsing" here is just reading that existing text layer — there is no
 * OCR. A scanned PDF (a photo of a page, with no text layer) yields little
 * or no text, so we treat a too-short extraction as a failure rather than
 * silently indexing near-nothing.
 */
class DocumentParserService
{
    private const MIN_EXTRACTED_CHARACTERS = 100;

    public function __construct(private readonly Parser $parser = new Parser) {}

    /**
     * @return array{text: string, page_count: int}
     */
    public function parse(string $diskPath): array
    {
        $pdf = $this->parser->parseFile(Storage::disk('local')->path($diskPath));

        $text = $this->normalizeWhitespace($pdf->getText());
        $pageCount = count($pdf->getPages());

        if (mb_strlen($text) < self::MIN_EXTRACTED_CHARACTERS) {
            throw new DocumentProcessingException(
                'This PDF appears to be scanned; text could not be extracted.'
            );
        }

        return [
            'text' => $text,
            'page_count' => $pageCount,
        ];
    }

    private function normalizeWhitespace(string $text): string
    {
        // Some PDF renderers pad a "blank" line with trailing spaces
        // (justified text) instead of leaving it truly empty, so a
        // whitespace-only line must be blanked out first — otherwise it
        // never matches as a paragraph break below, or in ChunkingService.
        $text = preg_replace('/^[ \t]+$/m', '', $text);

        // Collapse runs of stray spaces/tabs and blank-line runs down to
        // single spaces and blank-line pairs, giving ChunkingService clean
        // paragraph boundaries to split on.
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
    }
}
