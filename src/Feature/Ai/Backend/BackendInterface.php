<?php

declare(strict_types=1);

namespace Noem\State\Feature\Ai\Backend;

use Noem\State\Feature\Ai\Request;

/**
 * Backend abstraction contract for AI providers
 *
 * Establishes common interface for transforming generic Request objects
 * to provider-specific API formats and handling streaming responses.
 */
interface BackendInterface
{
    /**
     * Transform generic Request to provider-specific completion API format
     *
     * @param Request $request Generic request object
     * @return array Provider-specific request structure
     */
    public function createCompletionRequest(Request $request): array;

    /**
     * Transform generic Request to provider-specific chat API format
     *
     * @param Request $request Generic request object
     * @return array Provider-specific request structure with messages
     */
    public function createChatRequest(Request $request): array;

    /**
     * Stream responses from provider API
     *
     * Handles provider-specific streaming format, parsing responses
     * and yielding text chunks incrementally.
     *
     * @param Request $request Generic request object
     * @return iterable<string> Stream of text chunks
     */
    public function stream(Request $request): iterable;

    /**
     * Apply provider-specific prompt formatting
     *
     * Enables template-based prompt customization per backend,
     * applying provider-specific best practices and requirements.
     *
     * @param string $prompt Raw prompt text
     * @return string Formatted prompt text
     */
    public function formatSystemPrompt(string $prompt): string;
}
