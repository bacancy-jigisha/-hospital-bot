<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Google Gemini API connection
    |--------------------------------------------------------------------------
    |
    | Connection details for the Gemini API (generativelanguage.googleapis.com),
    | used for both embeddings and chat. Kept separate from config/rag.php,
    | which holds RAG-specific choices (which model, chunk size, ...) rather
    | than connection details.
    */

    'api_key' => env('GEMINI_API_KEY'),

    'base_uri' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),

];
