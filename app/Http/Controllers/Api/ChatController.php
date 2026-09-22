<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\LlmException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ChatRequest;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Services\RagService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ChatController extends Controller
{
    public function send(ChatRequest $request, RagService $rag): JsonResponse|MessageResource
    {
        try {
            $message = $rag->handle(
                $request->validated('session_id'),
                $request->validated('message'),
            );
        } catch (LlmException $e) {
            // The user's message and the failure are already logged inside
            // RagService/LlmService — this just keeps the real exception
            // out of the response.
            return response()->json(['message' => $e->getMessage()], 503);
        }

        return new MessageResource($message);
    }

    public function history(string $sessionId): AnonymousResourceCollection
    {
        $conversation = Conversation::where('session_id', $sessionId)->first();

        return MessageResource::collection(
            $conversation?->messages()->orderBy('id')->get() ?? collect()
        );
    }
}
