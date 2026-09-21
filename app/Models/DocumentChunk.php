<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentChunk extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id', 'chunk_index', 'content', 'embedding', 'token_estimate',
    ];

    protected function casts(): array
    {
        return [
            // The embedding vector, stored as a JSON array of floats. See
            // VectorSearchService for why v1 keeps vectors in MySQL instead
            // of a dedicated vector database.
            'embedding' => 'array',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
