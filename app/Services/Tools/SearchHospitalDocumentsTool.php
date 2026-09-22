<?php

namespace App\Services\Tools;

use App\Services\VectorSearchService;

/**
 * Searches the hospital's uploaded documents (handbooks, policies,
 * procedures) by meaning, not keyword — this is the tool that turns
 * "plain RAG" retrieval into one option among several the agent can
 * choose to use.
 */
class SearchHospitalDocumentsTool implements ToolInterface
{
    public function __construct(private readonly VectorSearchService $search) {}

    public function name(): string
    {
        return 'search_hospital_documents';
    }

    public function description(): string
    {
        return "Search the hospital's uploaded documents (policies, handbooks, procedures) for information relevant to a question. Returns the most relevant passages, each with its source document title and a relevance score. Use this for anything that sounds like it comes from written hospital policy or documentation, not from the departments/doctors/services tables.";
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'The question or topic to search for.',
                ],
                'category' => [
                    'type' => 'string',
                    'enum' => ['general', 'departments', 'doctors', 'services', 'admission', 'facilities', 'policies'],
                    'description' => 'Optional. Restrict the search to documents of this category.',
                ],
            ],
            'required' => ['query'],
        ];
    }

    public function execute(array $args): array
    {
        return $this->search->search(
            $args['query'],
            config('rag.top_k'),
            $args['category'] ?? null,
        );
    }
}
