<?php

declare(strict_types=1);

namespace Noem\State\Feature\Ai\Backend;

use Noem\State\Feature\Ai\Request;
use Noem\State\Feature\Async\IO\Fetch;

/**
 * Ollama API backend implementation
 *
 * Implements BackendInterface for Ollama's local deployment API,
 * handling Ollama-specific request structures and JSON line streaming format.
 */
class OllamaBackend implements BackendInterface
{
    public function __construct(
        private readonly ?string $baseUrl = null
    ) {
    }

    public function createCompletionRequest(Request $request): array
    {
        return [
            'model' => $request->model,
            'prompt' => $request->prompt,
            'stream' => $request->stream,
            'options' => [
                'temperature' => $request->temperature,
                'num_predict' => $request->maxTokens,
                'stop' => $request->stop ? [$request->stop] : null,
            ],
        ];
    }

    public function createChatRequest(Request $request): array
    {
        $args = [
            'model' => $request->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a helpful assistant',
                ],
                [
                    'role' => 'user',
                    'content' => $request->prompt,
                ],
            ],
            // Disable streaming for JSON format to avoid partial JSON chunks
            'stream' => $request->responseFormat ? false : $request->stream,
            'options' => [
                'temperature' => $request->temperature,
                'num_predict' => $request->maxTokens,
            ],
        ];

        if ($request->responseFormat) {
            $args['format'] = 'json';
        }

        return $args;
    }

    public function stream(Request $request): iterable
    {
        $baseUrl = $this->baseUrl ?? 'http://localhost:11434/api';

        // Determine endpoint based on request type
        // Use chat endpoint for structured output (JSON schema)
        $useChat = $request->responseFormat !== null;
        $endpoint = $useChat ? "{$baseUrl}/chat" : "{$baseUrl}/generate";

        $requestData = $useChat
            ? $this->createChatRequest($request)
            : $this->createCompletionRequest($request);

        $fetch = new Fetch(
            $endpoint,
            'POST',
            [
                'Content-Type' => 'application/json',
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
                if ($line !== '') {
                    $decoded = json_decode($line, true);
                    if ($decoded) {
                        // Normalize Ollama format to OpenAI format
                        yield $this->normalizeResponse($decoded, $useChat);
                    }
                }
            }
            $buffer = end($lines);
        }

        // Process remaining buffer
        if ($buffer !== '') {
            $decoded = json_decode($buffer, true);
            if ($decoded) {
                // Normalize Ollama format to OpenAI format
                yield $this->normalizeResponse($decoded, $useChat);
            }
        }
    }

    /**
     * Normalize Ollama API response to OpenAI-compatible format
     *
     * @param array $response Raw Ollama response
     * @param bool $isChat Whether this is a chat response
     * @return array Normalized response
     */
    private function normalizeResponse(array $response, bool $isChat): array
    {
        if ($isChat && isset($response['message'])) {
            // Ollama chat format: {message: {role, content}}
            // Convert to OpenAI format: {choices: [{message: {role, content}}]}
            return [
                'choices' => [
                    [
                        'message' => $response['message'],
                    ],
                ],
            ];
        }

        if (!$isChat && isset($response['response'])) {
            // Ollama completion format: {response: "..."}
            // Convert to OpenAI format: {choices: [{message: {content: "..."}}]}
            return [
                'choices' => [
                    [
                        'message' => [
                            'content' => $response['response'],
                        ],
                    ],
                ],
            ];
        }

        // Return as-is if format not recognized
        return $response;
    }

    public function formatSystemPrompt(string $prompt): string
    {
        // Ollama-specific prompt formatting
        // Local models may benefit from different formatting
        return $prompt;
    }
}
