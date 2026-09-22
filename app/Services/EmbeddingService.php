<?php

namespace App\Services;

use App\Exceptions\DocumentProcessingException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Turns text into embedding vectors via the Gemini API's batchEmbedContents
 * endpoint.
 *
 * An "embedding" is a list of floats that represents a piece of text's
 * meaning as a point in a high-dimensional space — texts about similar
 * things end up as nearby points. That's the property VectorSearchService
 * later exploits: finding chunks "near" a question's embedding is finding
 * chunks that mean something similar to the question.
 *
 * There is no first-party Gemini PHP SDK in wide use, so this calls the
 * REST API directly with Laravel's HTTP client rather than adding an
 * unverified third-party dependency.
 */
class EmbeddingService
{
    // batchEmbedContents accepts at most 100 requests per call (Gemini API
    // limit) — batching this many chunks per request, instead of one
    // request per chunk, is both faster and cheaper for the same work.
    private const BATCH_SIZE = 100;

    private const MAX_ATTEMPTS = 3;

    /**
     * @return array<int, float>
     */
    public function embed(string $text): array
    {
        return $this->embedBatch([$text])[0];
    }

    /**
     * @param  array<int, string>  $texts
     * @return array<int, array<int, float>>
     */
    public function embedBatch(array $texts): array
    {
        $vectors = [];

        foreach (array_chunk($texts, self::BATCH_SIZE) as $batch) {
            array_push($vectors, ...$this->requestWithRetry($batch));
        }

        return $vectors;
    }

    /**
     * @param  array<int, string>  $batch
     * @return array<int, array<int, float>>
     */
    private function requestWithRetry(array $batch): array
    {
        $model = config('rag.embedding_model');

        $requests = array_map(fn (string $text) => [
            'model' => "models/{$model}",
            'content' => ['parts' => [['text' => $text]]],
        ], $batch);

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            try {
                $response = Http::withHeaders(['x-goog-api-key' => config('gemini.api_key')])
                    ->post(config('gemini.base_uri')."/models/{$model}:batchEmbedContents", [
                        'requests' => $requests,
                    ])
                    ->throw();

                return array_map(
                    fn (array $embedding) => $embedding['values'],
                    $response->json('embeddings')
                );
            } catch (RequestException $e) {
                $status = $e->response->status();
                $retryable = $status === 429 || $status >= 500;

                if (! $retryable || $attempt === self::MAX_ATTEMPTS) {
                    Log::error('EmbeddingService: embedding request failed', [
                        'attempt' => $attempt,
                        'status' => $status,
                        'body' => $e->response->body(),
                    ]);

                    throw new DocumentProcessingException(
                        'Failed to generate embeddings — the embedding service is unavailable. Please try again later.',
                        previous: $e,
                    );
                }

                // Exponential backoff: 1s, 2s, 4s...
                usleep((2 ** ($attempt - 1)) * 1_000_000);
            }
        }

        // Unreachable: the loop above always returns or throws by the time
        // $attempt reaches MAX_ATTEMPTS. Satisfies the return type for
        // static analysis.
        throw new DocumentProcessingException('Failed to generate embeddings.');
    }
}
