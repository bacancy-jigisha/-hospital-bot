<?php

namespace App\Exceptions;

use Exception;

/**
 * Anything that goes wrong turning an uploaded PDF into usable text or
 * chunks — a document-processing failure, as opposed to a framework or
 * infrastructure error. ProcessDocumentJob (Phase 5) catches this and marks
 * the document `failed` with the message here as the user-safe explanation.
 */
class DocumentProcessingException extends Exception {}
