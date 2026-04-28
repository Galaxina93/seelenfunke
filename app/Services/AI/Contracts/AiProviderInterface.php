<?php

namespace App\Services\AI\Contracts;

use App\Models\Ai\AiAgent;

interface AiProviderInterface
{
    /**
     * Initializes the provider with the specific Agent configuration.
     *
     * @param AiAgent $agent
     */
    public function __construct(AiAgent $agent);

    /**
     * Ask the model a specific prompt directly (without conversational context wrappers).
     *
     * @param AiAgent $agent
     * @param string $prompt
     * @return string
     */
    public static function processDirectPrompt(AiAgent $agent, string $prompt): string;

    /**
     * The main iterative conversation loop. Should handle memory context, 
     * tool calling, and returning the final textual response or events.
     *
     * @param array $incomingMessages The prepared messages array ready for LLM
     * @param \Closure|null $streamCallback Callback for live UI streaming
     * @return array Structure: ['text' => string, 'events' => array]
     */
    public function ask(array $incomingMessages, ?\Closure $streamCallback = null): array;
}
