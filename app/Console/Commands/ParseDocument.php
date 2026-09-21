<?php

namespace App\Console\Commands;

use App\Exceptions\DocumentProcessingException;
use App\Models\Document;
use App\Services\DocumentParserService;
use Illuminate\Console\Command;

class ParseDocument extends Command
{
    protected $signature = 'document:parse {id : The documents table id}';

    protected $description = "Extract and print a document's text, to manually verify DocumentParserService";

    public function handle(DocumentParserService $parser): int
    {
        $document = Document::find($this->argument('id'));

        if (! $document) {
            $this->error("No document with id {$this->argument('id')}.");

            return self::FAILURE;
        }

        try {
            $result = $parser->parse($document->file_path);
        } catch (DocumentProcessingException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Pages: {$result['page_count']}");
        $this->info('Characters extracted: '.mb_strlen($result['text']));
        $this->line('---');
        $this->line($result['text']);

        return self::SUCCESS;
    }
}
