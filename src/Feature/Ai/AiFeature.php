<?php

declare(strict_types=1);

namespace Noem\State\Feature\Ai;

use Noem\State\Feature\Ai\Backend\AnthropicBackend;
use Noem\State\Feature\Ai\Backend\BackendInterface;
use Noem\State\Feature\Ai\Backend\OllamaBackend;
use Noem\State\Feature\Ai\Backend\OpenAiBackend;
use Noem\State\Feature\Ai\Chains\PromptTemplate;
use Noem\State\Feature\Ai\Chains\SystemPrompt;
use Noem\State\Feature\Ai\Completion;
use Noem\State\Feature\Async\AsyncFeature;
use Noem\State\Feature\ExtendedState\ContextChains\BoundAccess;
use Noem\State\Feature\ExtendedState\ContextChains\Params\BoundAccessParams;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\Feature;
use Noem\State\Feature\RequiresFeature;
use Noem\State\Feature\Template\Compiler\Invocation;
use Noem\State\Feature\Template\Helpers;
use Noem\State\Feature\Template\TemplateFeature;
use Noem\State\Middleware\ChainMail;
use Noem\State\Middleware\Mesh;

#[RequiresFeature(ExtendedState::class)]
#[RequiresFeature(AsyncFeature::class)]
#[RequiresFeature(TemplateFeature::class)]
class AiFeature implements Feature
{
    /**
     * Resolves schema parameter to actual JSON schema array
     *
     * @param mixed $schemaParam Schema parameter from template or context call
     * @param array $invocationData Template data context for variable resolution
     * @return array Resolved JSON schema
     * @throws \RuntimeException When schema variable not found or invalid type
     */
    private function resolveSchema(mixed $schemaParam, array $invocationData): array
    {
        // Default schema: array of strings
        if ($schemaParam === null) {
            return [
                'type' => 'array',
                'items' => [
                    'type' => 'string',
                ],
            ];
        }

        // Context variable reference
        if (is_string($schemaParam)) {
            if (!isset($invocationData[$schemaParam])) {
                throw new \RuntimeException("Schema variable not found: {$schemaParam}");
            }
            $resolved = $invocationData[$schemaParam];
            if (!is_array($resolved)) {
                throw new \RuntimeException("Schema variable must be array, got: " . gettype($resolved));
            }
            $this->validateSchema($resolved);
            return $resolved;
        }

        // Inline schema object
        if (is_array($schemaParam)) {
            $this->validateSchema($schemaParam);
            return $schemaParam;
        }

        throw new \RuntimeException("Invalid schema parameter type: " . gettype($schemaParam));
    }

    /**
     * Validates that schema is a valid JSON Schema structure
     *
     * @param array $schema Schema to validate
     * @throws \RuntimeException When schema is invalid
     */
    private function validateSchema(array $schema): void
    {
        // Basic validation: must have 'type' property
        if (!isset($schema['type'])) {
            throw new \RuntimeException("Invalid JSON Schema: missing 'type' property");
        }

        // Validate type is string
        if (!is_string($schema['type'])) {
            throw new \RuntimeException("Invalid JSON Schema: 'type' must be string");
        }
    }

    /**
     * Resolves backend from parameter or defaults to 'openai'
     *
     * @param string|null $backendName Backend name from parameter
     * @param Mesh $backends Available backends mesh
     * @return BackendInterface Resolved backend instance
     */
    private function resolveBackend(?string $backendName, Mesh $backends): BackendInterface
    {
        $backendName = $backendName ?? 'openai';
        return $backends[$backendName] ?? $backends['openai'];
    }

    public function __invoke(ChainMail $chainMail): void
    {
        $chainMail->supply(fn(): SystemPrompt => new SystemPrompt());

        // Supply backend mesh
        $backendData = [
            'openai' => new OpenAiBackend(),
            'ollama' => new OllamaBackend(),
            'anthropic' => new AnthropicBackend(),
        ];
        $backends = new Mesh($backendData);
        $chainMail->supply(fn(): Mesh => $backends);

        // Supply prompt template chain
        $chainMail->supply(fn(): PromptTemplate => new PromptTemplate());

        // Register context capture() and complete() methods via BoundAccess chain
        // BoundAccess is optional - only available when ExtendedState is loaded
        $chainMail->use(
            function (
                ?BoundAccess $boundAccess,
                Mesh $backends,
                PromptTemplate $promptTemplate,
                ?ModelPool $modelPool = null
            ): void {
                // Skip if ExtendedState is not loaded
                if ($boundAccess === null) {
                    return;
                }
                $boundAccess->link(
                    function (
                        BoundAccessParams $params,
                        callable $next
                    ) use (
                        $backends,
                        $promptTemplate,
                        $modelPool
                    ) {
                        if ($params->type !== BoundAccessParams::TYPE_METHOD) {
                            return $next($params);
                        }

                        if ($params->name === 'capture') {
                            $args = $params->payload;
                            $prompt = array_shift($args);
                            $schema = array_shift($args);
                            $backend = array_shift($args) ?? 'openai';

                            // Resolve backend using shared logic
                            $backendInstance = $this->resolveBackend($backend, $backends);

                            // Resolve schema using shared logic (no invocation data for context calls)
                            $resolvedSchema = $this->resolveSchema($schema, []);

                            // Build request
                            $request = new RequestBuilder()
                                ->setPrompt($prompt)
                                ->setResponseFormat(
                                    new ResponseFormat(
                                        'json_schema',
                                        [
                                            'name' => 'result',
                                            'schema' => $resolvedSchema,
                                        ]
                                    )
                                )
                                ->build();

                            // Execute API call via Chat
                            $generator = new Chat($request, true, $backendInstance)();
                            $result = implode(iterator_to_array($generator, false));
                            $decoded = json_decode($result, true);

                            return $decoded;
                        }

                        if ($params->name === 'complete') {
                            $args = $params->payload;
                            $prompt = array_shift($args);
                            $backend = array_shift($args) ?? 'openai';

                            // Resolve backend using shared logic
                            $backendInstance = $this->resolveBackend($backend, $backends);

                            // Build request for text completion
                            $request = new RequestBuilder()
                                ->setPrompt($prompt)
                                ->build();

                            // Execute API call via Completion and return generator
                            $completion = new Completion($request, true, $backendInstance);
                            return $completion();
                        }

                        return $next($params);
                    }
                );
            }
        );

        $chainMail->use(
            function (Helpers $helpers, Mesh $backends, PromptTemplate $promptTemplate): void {
                $helpers->registerHelper(
                    'complete',
                    function (Invocation $invocation, callable $next) use ($backends, $promptTemplate) {
                    // Get backend from parameter or default to 'openai'
                        $backendName = $invocation->hash['backend'] ?? 'openai';
                        $backend = $backends[$backendName] ?? $backends['openai'];

                        $request = new RequestBuilder();

                        if (isset($invocation->hash['max'])) {
                            $request->setMaxTokens((int)$invocation->hash['max']);
                        }
                        if (isset($invocation->hash['stop'])) {
                            $request->setStop(trim($invocation->hash['stop'], '"\''));
                        }

                    // Get base prompt template
                        $basePrompt = $promptTemplate($backendName, 'completion');
                        $prompt = $basePrompt !== '' ? $basePrompt : "# Instruction\n\n";
                        $prompt .= <<<'DOC'
You are a text completion assistant. Your task is to extend the provided text based on the user's instructions.

Rules:
1. Return only the extended text, with no wrapping formatting or explanations.
2. The first character of your response must be the first character of the code.
3. The last character of your response must be the last character of the code.
4. Instead of using triple backticks (```) or any other markdown, 
assume the content will be formatted properly by the surrounding application
5. Do not use any code block indicators, syntax highlighting markers, or any other formatting characters.
6. Present the text exactly as it would appear in a plain text editor, 
preserving all whitespace, indentation, and line breaks.
7. Maintain the original code structure and only make changes as specified by the user's instructions.
8. Ensure that the extended text is syntactically and semantically correct for the given text type.
9. Use consistent indentation and follow inferred style guidelines.

## Context


DOC;

                        $prompt .= PHP_EOL;
                        if (!$invocation->isBlock()) {
                            $prompt .= 'Complete the document provided.';
                            $prompt .= PHP_EOL;
                            $prompt .= PHP_EOL;
                            $prompt .= 'Input:';
                            $prompt .= PHP_EOL;
                            $prompt .= PHP_EOL;
                            $prompt .= PHP_EOL;
                            $prompt .= $invocation->getBuffer();

                            $request->setPrompt($prompt);
                            $generator = new Completion($request->build(), true, $backend)();
                            while ($generator->valid()) {
                                $chunk = $generator->current();
                                yield $chunk;
                                $generator->next();
                            }
                            yield from $next($invocation);

                            return;
                        }
                        $prompt .= implode(iterator_to_array($invocation->blockContent(), false));
                        $prompt .= <<<'DOC'

## Document (for completion)


DOC;
                    //$prompt .= PHP_EOL;
                    //$prompt .= PHP_EOL;
                    //$prompt .= 'Input:';
                    //$prompt .= PHP_EOL;
                    //$prompt .= PHP_EOL;
                    //$prompt .= PHP_EOL;
                        $prompt .= $invocation->getBuffer();
                        $request->setPrompt($prompt);
                        yield '';
                        yield from new Completion($request->build(), true, $backend)();
                        yield from $next($invocation);
                    }
                );

                $helpers->registerHelper(
                    'capture',
                    function (Invocation $invocation, callable $next) use ($backends, $promptTemplate) {
                        $key = $invocation->args[0];

                        // Resolve backend using shared logic
                        $backendName = $invocation->hash['backend'] ?? null;
                        $backend = $this->resolveBackend($backendName, $backends);

                        // Resolve schema using shared logic
                        $schemaParam = $invocation->hash['schema'] ?? null;
                        $schema = $this->resolveSchema($schemaParam, $invocation->data);

                        // Get base prompt template
                        $actualBackendName = $backendName ?? 'openai';
                        $basePrompt = $promptTemplate($actualBackendName, 'chat');
                        $prompt = $basePrompt !== '' ? $basePrompt : '# Instructions';
                        $prompt .= PHP_EOL;
                        $prompt .= 'Extract data based on the provided input. Always respond in JSON format.';
                        if ($invocation->isBlock()) {
                            $prompt .= PHP_EOL;
                            $blockContent = implode(iterator_to_array($invocation->blockContent($invocation), false));
                            $prompt .= PHP_EOL . $blockContent;
                            $prompt .= PHP_EOL;
                        }
                        $prompt .= '# Input';
                        $prompt .= PHP_EOL;
                        $prompt .= $invocation->getBuffer();

                        $request = new RequestBuilder()
                            ->setPrompt($prompt)
                            ->setResponseFormat(
                                new ResponseFormat(
                                    'json_schema',
                                    [
                                        'name' => 'list',
                                        'schema' => $schema,
                                    ]
                                )
                            )
                            ->build();
                        $generator = new Chat($request, true, $backend)();
                        $result = implode(iterator_to_array($generator, false));
                        $decoded = json_decode($result, true);
                        $invocation->data[$key] = $decoded;
                        return yield from $next($invocation);
                    }
                );
            }
        );
    }
}
