<?php

namespace App\Jobs;

use App\Exceptions\DocumentProcessingException;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Services\ChunkingService;
use App\Services\DocumentParserService;
use App\Services\EmbeddingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The indexing pipeline for one uploaded document: extract text, chunk it,
 * embed each chunk, and store the chunks — turning a PDF into the
 * searchable pieces VectorSearchService later queries against.
 *
 * If handle() throws, Laravel retries the whole job up to $tries times
 * before giving up and calling failed() below.
 */
class ProcessDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(public Document $document) {}

    public function handle(
        DocumentParserService $parser,
        ChunkingService $chunker,
        EmbeddingService $embedder,
    ): void {
        $this->document->update(['status' => 'processing']);

        $parsed = $parser->parse($this->document->file_path);
        $chunkTexts = $chunker->chunk($parsed['text'], $this->document->title);
        $embeddings = $embedder->embedBatch($chunkTexts);

        DB::transaction(function () use ($chunkTexts, $embeddings, $parsed) {
            foreach ($chunkTexts as $index => $content) {
                DocumentChunk::create([
                    'document_id' => $this->document->id,
                    'chunk_index' => $index,
                    'content' => $content,
                    'embedding' => $embeddings[$index],
                    // A rough approximation (~4 characters per English
                    // token) for display purposes — not an exact tokenizer
                    // count, which would need a separate dependency.
                    'token_estimate' => (int) ceil(mb_strlen($content) / 4),
                ]);
            }

            $this->document->update([
                'status' => 'completed',
                'page_count' => $parsed['page_count'],
                'chunk_count' => count($chunkTexts),
            ]);
        });
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('ProcessDocumentJob failed', [
            'document_id' => $this->document->id,
            'exception' => (string) $exception,
        ]);

        // DocumentProcessingException messages are already written to be
        // shown to a user (e.g. "This PDF appears to be scanned...");
        // anything else is an unexpected/infrastructure failure, so a
        // generic message is shown instead of leaking its details.
        $message = $exception instanceof DocumentProcessingException
            ? $exception->getMessage()
            : 'This document could not be processed. Please try again or contact support.';

        $this->document->update([
            'status' => 'failed',
            'error_message' => $message,
        ]);
    }
}
