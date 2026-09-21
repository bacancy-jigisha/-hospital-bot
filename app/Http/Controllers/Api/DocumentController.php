<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UploadDocumentRequest;
use App\Http\Resources\DocumentResource;
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

        return (new DocumentResource($document))->response()->setStatusCode(201);
    }
}
