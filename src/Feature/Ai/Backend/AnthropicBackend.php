<?php

declare(strict_types=1);

namespace Noem\State\Feature\Ai\Backend;

use Noem\State\Feature\Ai\Request;
use Noem\State\Feature\Async\IO\Fetch;

/**
 * Anthropic (Claude) API backend implementation
 *
 * Implements BackendInterface for Anthropic's Messages API,
 * handling Claude-specific request structures and SSE streaming format.
 */
class AnthropicBackend implements BackendInterface
{
    public function __construct(
        private readonly ?string $apiKey = null,
        private readonly ?string $baseUrl = null
    ) {
    }

    public function createCompletionRequest(Request $request): array
    {
        // Anthropic uses Messages API for everything
        // Treat completion as a single-turn chat
        return [
            'model' => $request->model,
            'max_tokens' => $request->maxTokens,
            'temperature' => $request->temperature,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $request->prompt,
                ],
            ],
            'stream' => $request->stream,
        ];
    }

    public function createChatRequest(Request $request): array
    {
        $args = [
            'model' => $request->model,
            'max_tokens' => $request->maxTokens,
            'temperature' => $request->temperature,
            'system' => 'You are a helpful assistant',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $request->prompt,
                ],
            ],
            'stream' => $request->stream,
        ];

        // Anthropic doesn't use response_format in the same way
        // JSON mode is requested via system prompt
        if ($request->responseFormat) {
            $args['system'] .= "\n\nRespond only with valid JSON.";
        }

        return $args;
    }

    public function stream(Request $request): iterable
    {
        $baseUrl = $this->baseUrl ?? 'https://api.anthropic.com/v1';
        $apiKey = $this->apiKey ?? $request->token;

        // Anthropic uses /messages endpoint
        $endpoint = "{$baseUrl}/messages";

        $requestData = $this->createChatRequest($request);

        $fetch = new Fetch(
            $endpoint,
            'POST',
            [
                'Content-Type' => 'application/json',
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
            ],
            json_encode($requestData)
        );

        $buffer = '';
        $generator = $fetch();

        while ($generator->valid()) {
            $chunk = $generator->current();
            $generator->next();
            $buffer .= $chunk;

            $lines = explode("\n", $buffer);
            foreach (array_slice($lines, 0, -1) as $line) {
                // Anthropic uses SSE format: "event: message_start", "data: {...}"
                if (strpos($line, 'data: ') === 0) {
                    $line = substr($line, strlen('data: '));
                    if ($line !== '') {
                        $decoded = json_decode($line, true);
                        if ($decoded) {
                            yield $decoded;
                        }
                    }
                }
            }
            $buffer = end($lines);
        }

        // Process remaining buffer
        if ($buffer !== '' && strpos($buffer, 'data: ') === 0) {
            $line = substr($buffer, strlen('data: '));
            $decoded = json_decode($line, true);
            if ($decoded) {
                yield $decoded;
            }
        }
    }

    public function formatSystemPrompt(string $prompt): string
    {
        // Claude-specific prompt formatting
        // Claude benefits from XML-style structuring
        return $prompt;
    }
}
