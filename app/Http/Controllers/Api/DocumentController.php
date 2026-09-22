<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UploadDocumentRequest;
use App\Http\Resources\DocumentResource;
use App\Jobs\ProcessDocumentJob;
use App\Models\Document;

class DocumentController extends Controller
{
    public function index()
    {
        return DocumentResource::collection(Document::latest()->get());
    }

    public function store(UploadDocumentRequest $request)
    {
        $file = $request->file('file');

        // ->store() generates a random filename itself — the visitor's
        // original filename is never used as a path, only kept below as a
        // display label. This is what "never trust the original filename"
        // means in practice.
        $path = $file->store('documents', 'local');

        $document = Document::create([
            'title' => $request->validated('title'),
            'original_filename' => $file->getClientOriginalName(),
            'file_path' => $path,
            'category' => $request->validated('category'),
            'status' => 'pending',
            'chunk_count' => 0,
        ]);

        try {
            ProcessDocumentJob::dispatch($document);
        } catch (\Throwable) {
            // With QUEUE_CONNECTION=sync the job above already ran inline,
            // and Laravel's sync driver calls the job's failed() method
            // (which already marked this document `failed` with a
            // user-safe message) but then re-throws the original
            // exception to the caller — there's no separate worker to own
            // it silently. The upload itself still succeeded, so this is
            // swallowed here rather than surfacing as a 500; the response
            // below reports the real outcome. With a real queue this
            // catch is simply never reached.
        }

        // With QUEUE_CONNECTION=sync the job above already ran to
        // completion against a separate model instance (SerializesModels
        // re-fetches it from the database) — refresh so the response
        // reflects the real outcome instead of stale in-memory attributes.
        // With a real queue this is a no-op; the row is still "pending"
        // and the frontend picks up completion via polling.
        return (new DocumentResource($document->refresh()))->response()->setStatusCode(201);
    }
}
