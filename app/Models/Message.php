<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use HasFactory;

    protected $fillable = ['conversation_id', 'role', 'content', 'sources', 'tool_trace'];

    protected function casts(): array
    {
        return [
            // Retrieved chunks cited for this answer, and the agent's tool
            // call trace (tool name, arguments, duration, result size) for
            // the "how this answer was produced" panel in the UI.
            'sources' => 'array',
            'tool_trace' => 'array',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
