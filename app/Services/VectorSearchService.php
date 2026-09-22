<?php

namespace App\Services;

use App\Models\DocumentChunk;

/**
 * Finds the document chunks most similar in meaning to a query, by
 * embedding the query and comparing it against every chunk's stored
 * embedding with cosine similarity.
 *
 * v1 keeps vectors in an ordinary MySQL JSON column and computes
 * similarity in PHP, rather than in a dedicated vector database — zero
 * extra infrastructure, and fine at this scale (a few thousand chunks).
 * This is the one place that knows vectors live in MySQL; everything
 * outside this service just calls search() and gets plain arrays back, so
 * swapping in pgvector or Qdrant later touches only this file. Revisit
 * once brute-force scanning every chunk on every query stops being fast
 * enough — likely tens of thousands of chunks or more.
 */
class VectorSearchService
{
    public function __construct(private readonly EmbeddingService $embedder) {}

    /**
     * @return array<int, array{chunk_id: int, document_id: int, document_title: string, content: string, score: float}>
     */
    public function search(string $query, int $topK = 5, ?string $category = null): array
    {
        $queryVector = $this->embedder->embed($query);

        $scored = [];

        DocumentChunk::query()
            ->with('document')
            ->when($category, fn ($q) => $q->whereHas(
                'document',
                fn ($q) => $q->where('category', $category)
            ))
            ->chunkById(500, function ($chunks) use (&$scored, $queryVector) {
                foreach ($chunks as $chunk) {
                    $score = $this->cosineSimilarity($queryVector, $chunk->embedding);

                    // Dropping low-scoring chunks here, not just capping at
                    // topK, matters: without it, a query with no relevant
                    // chunks at all would still return its "least
                    // dissimilar" ones, which is worse than saying nothing
                    // was found.
                    if ($score >= config('rag.min_similarity')) {
                        $scored[] = [
                            'chunk_id' => $chunk->id,
                            'document_id' => $chunk->document_id,
                            'document_title' => $chunk->document->title,
                            'content' => $chunk->content,
                            'score' => $score,
                        ];
                    }
                }
            });

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, $topK);
    }

    /**
     * Cosine similarity: the cosine of the angle between two vectors,
     * from -1 (opposite meaning) to 1 (identical direction); 0 means
     * unrelated. Formula: dot(a, b) / (|a| * |b|). Because an embedding
     * encodes meaning as *direction* in high-dimensional space, this
     * measures how similar in meaning two texts are, independent of
     * their length.
     *
     * @param  array<int, float>  $a
     * @param  array<int, float>  $b
     */
    private function cosineSimilarity(array $a, array $b): float
    {
        $dotProduct = 0.0;
        $magnitudeA = 0.0;
        $magnitudeB = 0.0;

        foreach ($a as $i => $value) {
            $dotProduct += $value * $b[$i];
            $magnitudeA += $value ** 2;
            $magnitudeB += $b[$i] ** 2;
        }

        if ($magnitudeA === 0.0 || $magnitudeB === 0.0) {
            return 0.0;
        }

        return $dotProduct / (sqrt($magnitudeA) * sqrt($magnitudeB));
    }
}
