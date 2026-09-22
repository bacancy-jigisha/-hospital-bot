<?php

namespace App\Services\Tools;

/**
 * One capability the agent can choose to call. AgentService builds the
 * tool definitions the LLM sees by iterating every registered tool's
 * name()/description()/parameters(), and dispatches a tool call to the
 * matching tool's execute().
 */
interface ToolInterface
{
    public function name(): string;

    public function description(): string;

    /**
     * JSON Schema describing this tool's arguments.
     *
     * @return array<string, mixed>
     */
    public function parameters(): array;

    /**
     * @param  array<string, mixed>  $args
     * @return array<int|string, mixed> An empty array is a normal "nothing found" result, not an error.
     */
    public function execute(array $args): array;
}
