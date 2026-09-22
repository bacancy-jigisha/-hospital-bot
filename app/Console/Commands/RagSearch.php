<?php

namespace App\Console\Commands;

use App\Services\VectorSearchService;
use Illuminate\Console\Command;

class RagSearch extends Command
{
    protected $signature = 'rag:search {query} {--category=} {--top-k=}';

    protected $description = 'Run a query through VectorSearchService and print the matching chunks with scores, to manually verify retrieval';

    public function handle(VectorSearchService $search): int
    {
        $topK = $this->option('top-k') !== null
            ? (int) $this->option('top-k')
            : config('rag.top_k');

        $results = $search->search(
            $this->argument('query'),
            $topK,
            $this->option('category'),
        );

        if ($results === []) {
            // Not an error — it means nothing in the knowledge base is
            // relevant to this query, which is a real, expected answer.
            $this->info('No relevant chunks found.');

            return self::SUCCESS;
        }

        foreach ($results as $result) {
            $this->line(sprintf(
                '[%.3f] (%s) chunk #%d',
                $result['score'],
                $result['document_title'],
                $result['chunk_id'],
            ));
            $this->line($result['content']);
            $this->line('---');
        }

        return self::SUCCESS;
    }
}
