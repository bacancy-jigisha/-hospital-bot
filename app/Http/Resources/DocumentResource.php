<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'original_filename' => $this->original_filename,
            'category' => $this->category,
            'status' => $this->status,
            'error_message' => $this->error_message,
            'page_count' => $this->page_count,
            'chunk_count' => $this->chunk_count,
            'created_at' => $this->created_at,
        ];
    }
}
