<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Department;
use App\Models\Message;

/**
 * Orchestrates one chat turn end to end: find or create the conversation,
 * screen for an emergency, run the agent (or skip straight to a hardcoded
 * emergency reply), and persist both the visitor's message and the
 * assistant's answer.
 */
class RagService
{
    // "Last 6 messages" per the spec — enough context for a natural
    // follow-up question without the prompt (and cost) growing with every
    // turn of a long conversation.
    private const HISTORY_LIMIT = 6;

    public function __construct(
        private readonly EmergencyDetector $emergencyDetector,
        private readonly AgentService $agent,
    ) {}

    public function handle(string $sessionId, string $question): Message
    {
        $conversation = Conversation::firstOrCreate(['session_id' => $sessionId]);

        $userMessage = $conversation->messages()->create([
            'role' => 'user',
            'content' => $question,
        ]);

        if ($this->emergencyDetector->matches($question)) {
            return $this->respondToEmergency($conversation);
        }

        $history = $conversation->messages()
            ->where('id', '!=', $userMessage->id)
            ->latest()
            ->take(self::HISTORY_LIMIT)
            ->get()
            ->reverse()
            ->map(fn (Message $message) => ['role' => $message->role, 'content' => $message->content])
            ->all();

        $result = $this->agent->respond($this->systemPrompt(), $history, $question);

        return $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $result['content'],
            'sources' => $result['sources'],
            'tool_trace' => $result['tool_trace'],
        ]);
    }

    private function respondToEmergency(Conversation $conversation): Message
    {
        $department = Department::where('is_emergency', true)->first();
        $location = $department ? " at {$department->building}, {$department->floor}" : '';

        $content = sprintf(
            'This may describe a medical emergency. Please call %s immediately, or go to the Emergency Department%s.',
            config('rag.emergency_phone'),
            $location,
        );

        // Logged like any other turn (still visible in chat history), but
        // never reaches the LLM — a false positive here costs nothing, so
        // there's no reason to spend a model call confirming what the
        // keyword match already found.
        return $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $content,
            'sources' => [],
            'tool_trace' => [[
                'tool' => 'emergency_detector',
                'arguments' => [],
                'duration_ms' => 0,
                'result_size' => 1,
            ]],
        ]);
    }

    private function systemPrompt(): string
    {
        $name = config('rag.hospital_name');
        $reception = config('rag.reception_phone');
        $emergency = config('rag.emergency_phone');

        return <<<PROMPT
            You are the information assistant for {$name}.

            RULES
            1. Answer ONLY from information returned by your tools. Never use
               outside knowledge about this hospital.
            2. If the tools return nothing relevant, say plainly that the
               information was not found in the hospital's documents and
               suggest calling reception on {$reception}. Do not guess.
            3. Never diagnose, recommend treatment, interpret test results, or
               advise on medication. Redirect to a qualified doctor and offer
               to help find the right department.
            4. If the message suggests a medical emergency, lead with
               {$emergency} and the emergency department location.
            5. Never give information about a specific patient.
            6. Cite the document title for anything taken from a document.
            7. Be brief and factual.
            PROMPT;
    }
}
