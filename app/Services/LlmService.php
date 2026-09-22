<?php

namespace App\Services;

use App\Exceptions\LlmException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * The only class that talks to the Gemini chat endpoint (generateContent).
 * AgentService and RagService exchange plain PHP arrays with this service
 * and never see Gemini's request/response shapes — so swapping chat
 * providers later only touches this file.
 *
 * Messages use a small, provider-agnostic shape:
 *   ['role' => 'user', 'content' => string]
 *   ['role' => 'assistant', 'content' => string]                     (plain answer)
 *   ['role' => 'assistant', 'tool_calls' => [['id','name','arguments']]]  (wants tools)
 *   ['role' => 'tool', 'tool_call_id' => string, 'name' => string, 'content' => array]
 */
class LlmService
{
    // A hospital information assistant should answer the same grounded
    // question the same way, not creatively — low temperature favours
    // consistency over inventive variation.
    private const TEMPERATURE = 0.2;

    // Observed during Phase 7 testing: gemini-3.8-flash (being the newest
    // model) intermittently returns 503 "high demand" even on otherwise
    // valid requests. Retrying is the same fix EmbeddingService already
    // uses for the same class of problem.
    private const MAX_ATTEMPTS = 3;

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<int, array{name: string, description: string, parameters: array}>  $tools
     * @return array{text: ?string, tool_calls: array<int, array{id: string, name: string, arguments: array}>}
     */
    public function chat(array $messages, string $systemPrompt, array $tools = []): array
    {
        $model = config('rag.chat_model');

        $body = [
            'contents' => $this->toGeminiContents($messages),
            'systemInstruction' => ['parts' => [['text' => $systemPrompt]]],
            'generationConfig' => ['temperature' => self::TEMPERATURE],
        ];

        if ($tools !== []) {
            $body['tools'] = [['functionDeclarations' => array_map(
                fn (array $tool) => [
                    'name' => $tool['name'],
                    'description' => $tool['description'],
                    'parameters' => $tool['parameters'],
                ],
                $tools
            )]];
        }

        return $this->fromGeminiResponse($this->requestWithRetry($model, $body));
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function requestWithRetry(string $model, array $body): array
    {
        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            try {
                $response = Http::withHeaders(['x-goog-api-key' => config('gemini.api_key')])
                    ->timeout(60)
                    ->post(config('gemini.base_uri')."/models/{$model}:generateContent", $body)
                    ->throw();

                return $response->json();
            } catch (RequestException $e) {
                $status = $e->response->status();
                $retryable = $status === 429 || $status >= 500;

                if (! $retryable || $attempt === self::MAX_ATTEMPTS) {
                    Log::error('LlmService: generateContent request failed', [
                        'attempt' => $attempt,
                        'status' => $status,
                        'body' => $e->response->body(),
                    ]);

                    throw new LlmException(
                        'The assistant is temporarily unavailable. Please try again shortly.',
                        previous: $e,
                    );
                }

                usleep((2 ** ($attempt - 1)) * 1_000_000);
            }
        }

        // Unreachable: the loop above always returns or throws by the
        // time $attempt reaches MAX_ATTEMPTS.
        throw new LlmException('The assistant is temporarily unavailable. Please try again shortly.');
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @return array<int, array<string, mixed>>
     */
    private function toGeminiContents(array $messages): array
    {
        return array_map(function (array $message) {
            if ($message['role'] === 'user') {
                return ['role' => 'user', 'parts' => [['text' => $message['content']]]];
            }

            if ($message['role'] === 'assistant') {
                if (isset($message['tool_calls'])) {
                    return ['role' => 'model', 'parts' => array_map(function (array $call) {
                        $part = ['functionCall' => [
                            'id' => $call['id'],
                            'name' => $call['name'],
                            // (object) forces {} rather than [] when a
                            // tool was called with no arguments — Gemini
                            // rejects a JSON array here ("Proto field is
                            // not repeating, cannot start list"), since
                            // args must be an object.
                            'args' => (object) $call['arguments'],
                        ]];

                        // Gemini 3's "thinking" models require the exact
                        // thoughtSignature they attached to a functionCall
                        // part to be echoed back — as a sibling of
                        // functionCall, not nested inside it — when that
                        // call is replayed in a later turn, or the request
                        // is rejected outright ("missing a
                        // thought_signature in functionCall parts").
                        if (isset($call['thought_signature'])) {
                            $part['thoughtSignature'] = $call['thought_signature'];
                        }

                        return $part;
                    }, $message['tool_calls'])];
                }

                return ['role' => 'model', 'parts' => [['text' => $message['content']]]];
            }

            // 'tool' — a function's result. Gemini expects this as a
            // "user" turn carrying a functionResponse part, not a
            // separate role. functionResponse.response must be a JSON
            // *object* (protobuf Struct) — several of our tools return a
            // plain list (e.g. search results), which Gemini rejects
            // ("Proto field is not repeating, cannot start list") unless
            // wrapped in an object first.
            return ['role' => 'user', 'parts' => [['functionResponse' => [
                'id' => $message['tool_call_id'],
                'name' => $message['name'],
                'response' => ['result' => $message['content']],
            ]]]];
        }, $messages);
    }

    /**
     * @return array{text: ?string, tool_calls: array<int, array{id: string, name: string, arguments: array}>}
     */
    private function fromGeminiResponse(array $response): array
    {
        $parts = $response['candidates'][0]['content']['parts'] ?? [];

        $toolCalls = [];
        $text = '';

        foreach ($parts as $part) {
            if (isset($part['functionCall'])) {
                $call = [
                    // Not every response includes a call id; the name is
                    // enough to correlate a single call with its result.
                    'id' => $part['functionCall']['id'] ?? $part['functionCall']['name'],
                    'name' => $part['functionCall']['name'],
                    'arguments' => $part['functionCall']['args'] ?? [],
                ];

                if (isset($part['thoughtSignature'])) {
                    $call['thought_signature'] = $part['thoughtSignature'];
                }

                $toolCalls[] = $call;
            } elseif (isset($part['text'])) {
                $text .= $part['text'];
            }
        }

        return [
            'text' => $toolCalls === [] ? $text : null,
            'tool_calls' => $toolCalls,
        ];
    }
}
