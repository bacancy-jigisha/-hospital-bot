<?php

namespace App\Services;

/**
 * A fast, deterministic keyword screen that runs before the agent loop.
 * A false positive here costs nothing — the visitor just sees the
 * emergency number a beat early. A false negative could mean someone in a
 * real emergency waits on an LLM round-trip instead of being told to get
 * help immediately. That asymmetry is why this is a plain keyword match,
 * not a judgment call left to the model.
 */
class EmergencyDetector
{
    public function matches(string $message): bool
    {
        $message = strtolower($message);

        foreach (config('rag.emergency_keywords') as $keyword) {
            if (str_contains($message, strtolower($keyword))) {
                return true;
            }
        }

        return false;
    }
}
