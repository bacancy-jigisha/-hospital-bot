<?php

namespace App\Services;

/**
 * Splits a document's text into overlapping chunks sized for embedding.
 *
 * A "chunk" is just a slice of a document short enough to embed and compare
 * meaningfully — the whole document is too broad a unit for similarity
 * search to be useful, so it gets cut into smaller pieces first.
 */
class ChunkingService
{
    private const MIN_CHUNK_CHARACTERS = 50;

    private readonly int $chunkSize;

    private readonly int $chunkOverlap;

    public function __construct(?int $chunkSize = null, ?int $chunkOverlap = null)
    {
        $this->chunkSize = $chunkSize ?? config('rag.chunk_size');
        $this->chunkOverlap = $chunkOverlap ?? config('rag.chunk_overlap');
    }

    /**
     * @return list<string>
     */
    public function chunk(string $text, string $documentTitle): array
    {
        $paragraphs = $this->splitIntoParagraphs($text);

        $chunks = [];
        $current = '';

        foreach ($paragraphs as $paragraph) {
            $candidate = $current === '' ? $paragraph : "{$current}\n\n{$paragraph}";

            if ($current !== '' && mb_strlen($candidate) > $this->chunkSize) {
                $chunks[] = $current;

                // Carry the tail of the finished chunk into the next one so
                // a sentence split across the boundary is still retrievable
                // from either chunk, instead of being cut cleanly in half.
                $overlap = mb_substr($current, -$this->chunkOverlap);
                $current = "{$overlap}\n\n{$paragraph}";
            } else {
                $current = $candidate;
            }
        }

        if ($current !== '') {
            $chunks[] = $current;
        }

        $chunks = array_values(array_filter(
            $chunks,
            fn (string $c) => mb_strlen(trim($c)) >= self::MIN_CHUNK_CHARACTERS
        ));

        // Prefixing every chunk with the document's title means the chunk's
        // embedding also carries *what document this is about*, not just
        // the sentence content — "10am-7pm daily" is much easier to
        // retrieve correctly when the embedded text also says
        // "[Admission Policy 2026] 10am-7pm daily" than as four isolated
        // words with no topic of their own.
        return array_map(
            fn (string $c) => "[{$documentTitle}] ".trim($c),
            $chunks
        );
    }

    /**
     * @return list<string>
     */
    private function splitIntoParagraphs(string $text): array
    {
        $paragraphs = preg_split('/\n{2,}/', trim($text)) ?: [];

        return array_values(array_filter(
            array_map('trim', $paragraphs),
            fn (string $p) => $p !== ''
        ));
    }
}
