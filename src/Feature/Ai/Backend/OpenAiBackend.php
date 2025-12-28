<?php

declare(strict_types=1);

namespace Noem\State\Feature\Ai\Backend;

use Noem\State\Feature\Ai\Request;
use Noem\State\Feature\Async\IO\Fetch;

/**
 * OpenAI API backend implementation
 *
 * Implements BackendInterface for OpenAI's completion and chat endpoints,
 * handling OpenAI-specific request structures and SSE streaming format.
 */
class OpenAiBackend implements BackendInterface
{
    public function __construct(
        private readonly ?string $apiKey = null,
        private readonly ?string $baseUrl = null
    ) {
    }

    public function createCompletionRequest(Request $request): array
    {
        return [
            'model' => $request->model,
            'prompt' => $request->prompt,
            'stream' => $request->stream,
            'stop' => $request->stop,
            'max_tokens' => $request->maxTokens,
            'temperature' => $request->temperature,
            'frequency_penalty' => 1.5,
            'suffix' => '',
        ];
    }

    public function createChatRequest(Request $request): array
    {
        $args = [
            'model' => $request->model,
            'messages' => [
                [
                    'role' => 'developer',
                    'content' => 'You are a helpful assistant',
                ],
                [
                    'role' => 'user',
                    'content' => $request->prompt,
                ],
            ],
            'stream' => $request->stream,
        ];

        if ($request->responseFormat) {
            $args['response_format'] = [
                'type' => $request->responseFormat->format,
                $request->responseFormat->format => $request->responseFormat->definition,
            ];
        }

        return $args;
    }

    public function stream(Request $request): iterable
    {
        $baseUrl = $this->baseUrl ?? $request->baseUrl;
        $token = $this->apiKey ?? $request->token;

        // Determine endpoint based on request type (completion vs chat)
        // For now, we'll use completion endpoint - this will be refined when used
        $endpoint = "{$baseUrl}/completions";

        $requestData = $this->createCompletionRequest($request);

        $fetch = new Fetch(
            $endpoint,
            'POST',
            [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $token,
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
                if ($request->stream && strpos($line, 'data: ') === 0) {
                    $line = substr($line, strlen('data: '));
                }
                if ($line !== '') {
                    $decoded = json_decode($line, true);
                    if ($decoded) {
                        yield $decoded;
                    }
                }
            }
            $buffer = end($lines);
        }

        // Process remaining buffer
        if ($buffer !== '') {
            $decoded = json_decode($buffer, true);
            if ($decoded) {
                yield $decoded;
            }
        }
    }

    public function formatSystemPrompt(string $prompt): string
    {
        // OpenAI-specific prompt formatting
        // For now, return as-is - can be enhanced with templates
        return $prompt;
    }
}
