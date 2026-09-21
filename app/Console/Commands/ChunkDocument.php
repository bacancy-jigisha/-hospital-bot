<?php

namespace App\Console\Commands;

use App\Exceptions\DocumentProcessingException;
use App\Models\Document;
use App\Services\ChunkingService;
use App\Services\DocumentParserService;
use Illuminate\Console\Command;

class ChunkDocument extends Command
{
    protected $signature = 'document:chunk {id : The documents table id}';

    protected $description = 'Parse and chunk a document, printing the chunk count and first two chunks, to manually verify ChunkingService';

    public function handle(DocumentParserService $parser, ChunkingService $chunker): int
    {
        $document = Document::find($this->argument('id'));

        if (! $document) {
            $this->error("No document with id {$this->argument('id')}.");

            return self::FAILURE;
        }

        try {
            $parsed = $parser->parse($document->file_path);
        } catch (DocumentProcessingException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $chunks = $chunker->chunk($parsed['text'], $document->title);

        $this->info('Chunk count: '.count($chunks));
        $this->line('---');

        foreach (array_slice($chunks, 0, 2) as $index => $chunk) {
            $this->line("Chunk {$index} (".mb_strlen($chunk).' chars):');
            $this->line($chunk);
            $this->line('---');
        }

        return self::SUCCESS;
    }
}
