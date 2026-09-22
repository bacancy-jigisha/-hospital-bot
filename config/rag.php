<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Gemini models
    |--------------------------------------------------------------------------
    |
    | Which Gemini model to call for embeddings vs. chat completions. See
    | config/gemini.php for the API connection details (key, base URL).
    */

    'embedding_model' => env('GEMINI_EMBEDDING_MODEL'),
    'chat_model' => env('GEMINI_CHAT_MODEL'),

    /*
    |--------------------------------------------------------------------------
    | Chunking
    |--------------------------------------------------------------------------
    |
    | ChunkingService accumulates paragraphs up to chunk_size characters per
    | chunk, then carries the last chunk_overlap characters of a finished
    | chunk into the next one so a sentence split across a chunk boundary is
    | still retrievable from either side.
    */

    'chunk_size' => (int) env('RAG_CHUNK_SIZE', 1000),
    'chunk_overlap' => (int) env('RAG_CHUNK_OVERLAP', 200),

    /*
    |--------------------------------------------------------------------------
    | Retrieval
    |--------------------------------------------------------------------------
    |
    | top_k: how many chunks VectorSearchService returns at most.
    | min_similarity: chunks scoring below this cosine similarity are
    | dropped rather than returned as a weak, likely-irrelevant match.
    */

    'top_k' => (int) env('RAG_TOP_K', 5),
    'min_similarity' => (float) env('RAG_MIN_SIMILARITY', 0.35),

    /*
    |--------------------------------------------------------------------------
    | Agent
    |--------------------------------------------------------------------------
    |
    | The agent loop (system prompt + question -> LLM with tools -> execute
    | tool calls -> repeat) stops after this many tool-calling rounds and
    | makes one final call with tools disabled, so a confused agent can't
    | loop forever.
    */

    'agent_max_steps' => (int) env('AGENT_MAX_STEPS', 5),

    /*
    |--------------------------------------------------------------------------
    | Hospital identity
    |--------------------------------------------------------------------------
    |
    | Used in the system prompt and in hardcoded emergency responses.
    */

    'hospital_name' => env('HOSPITAL_NAME'),
    'reception_phone' => env('HOSPITAL_RECEPTION_PHONE'),
    'emergency_phone' => env('HOSPITAL_EMERGENCY_PHONE'),

];
