<?php

namespace App\Services;

use App\Services\Tools\FindDoctorsTool;
use App\Services\Tools\GetDepartmentInfoTool;
use App\Services\Tools\GetVisitingHoursTool;
use App\Services\Tools\ListServicesTool;
use App\Services\Tools\SearchHospitalDocumentsTool;
use App\Services\Tools\ToolInterface;

/**
 * The agent loop: give the LLM a system prompt, recent conversation, and a
 * set of tools; let it decide whether to call a tool and with what
 * arguments; feed the result back; repeat until it answers in plain text
 * or AGENT_MAX_STEPS is hit. This is what an "agent" adds over plain RAG,
 * which always performs exactly one fixed retrieval before answering.
 */
class AgentService
{
    /** @var array<string, ToolInterface> */
    private readonly array $tools;

    public function __construct(
        private readonly LlmService $llm,
        SearchHospitalDocumentsTool $searchHospitalDocuments,
        FindDoctorsTool $findDoctors,
        GetDepartmentInfoTool $getDepartmentInfo,
        ListServicesTool $listServices,
        GetVisitingHoursTool $getVisitingHours,
    ) {
        $tools = [];

        foreach ([$searchHospitalDocuments, $findDoctors, $getDepartmentInfo, $listServices, $getVisitingHours] as $tool) {
            $tools[$tool->name()] = $tool;
        }

        $this->tools = $tools;
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $history  Prior turns, oldest first — not including $question.
     * @return array{content: string, sources: array<int, array{document_title: string, score: float}>, tool_trace: array<int, array{tool: string, arguments: array, duration_ms: int, result_size: int}>}
     */
    public function respond(string $systemPrompt, array $history, string $question): array
    {
        $messages = $history;
        $messages[] = ['role' => 'user', 'content' => $question];

        $trace = [];
        $sources = [];
        $toolDefinitions = $this->toolDefinitions();

        for ($step = 0; $step < config('rag.agent_max_steps'); $step++) {
            $response = $this->llm->chat($messages, $systemPrompt, $toolDefinitions);

            if ($response['tool_calls'] === []) {
                return [
                    'content' => $response['text'] ?? '',
                    'sources' => $sources,
                    'tool_trace' => $trace,
                ];
            }

            $messages[] = ['role' => 'assistant', 'tool_calls' => $response['tool_calls']];

            foreach ($response['tool_calls'] as $call) {
                [$result, $durationMs] = $this->callTool($call['name'], $call['arguments']);

                $trace[] = [
                    'tool' => $call['name'],
                    'arguments' => $call['arguments'],
                    'duration_ms' => $durationMs,
                    'result_size' => count($result),
                ];

                if ($call['name'] === 'search_hospital_documents') {
                    foreach ($result as $chunk) {
                        $sources[] = [
                            'document_title' => $chunk['document_title'],
                            'score' => $chunk['score'],
                        ];
                    }
                }

                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $call['id'],
                    'name' => $call['name'],
                    'content' => $result,
                ];
            }
        }

        // AGENT_MAX_STEPS reached without a plain-text answer — one final
        // call with tools disabled forces a plain-text answer from
        // whatever's been gathered so far, instead of looping forever.
        $final = $this->llm->chat($messages, $systemPrompt, []);

        return [
            'content' => $final['text'] ?? 'I was unable to complete that request. Please contact reception for help.',
            'sources' => $sources,
            'tool_trace' => $trace,
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array{0: array<int|string, mixed>, 1: int} [result, duration_ms]
     */
    private function callTool(string $name, array $arguments): array
    {
        $tool = $this->tools[$name] ?? null;
        $started = microtime(true);

        $result = $tool
            ? $tool->execute($arguments)
            : ['error' => "Unknown tool: {$name}"];

        return [$result, (int) round((microtime(true) - $started) * 1000)];
    }

    /**
     * @return array<int, array{name: string, description: string, parameters: array}>
     */
    private function toolDefinitions(): array
    {
        return array_values(array_map(
            fn (ToolInterface $tool) => [
                'name' => $tool->name(),
                'description' => $tool->description(),
                'parameters' => $tool->parameters(),
            ],
            $this->tools
        ));
    }
}
