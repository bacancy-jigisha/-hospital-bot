<?php

namespace App\Exceptions;

use Exception;

/**
 * Anything that goes wrong calling the chat model — a network failure, a
 * non-2xx response, an unexpected response shape. Distinct from
 * DocumentProcessingException, which is about the indexing pipeline, not
 * the chat endpoint.
 */
class LlmException extends Exception {}
