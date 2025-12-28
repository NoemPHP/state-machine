<?php

declare(strict_types=1);

namespace Noem\State\Feature\Agentic\Chains;

use Noem\State\Feature\Abilities\AbilityRegistry;
use Noem\State\Feature\Abilities\Chains\InvokeAbility;
use Noem\State\Feature\Agentic\Chains\Params\Weave as WeaveParams;
use Noem\State\Feature\Agentic\WeaveConfig;
use Noem\State\Feature\Ai\Chat;
use Noem\State\Feature\Ai\Completion;
use Noem\State\Feature\Ai\RequestBuilder;
use Noem\State\Feature\Ai\ResponseFormat;
use Noem\State\Middleware\Chain;
use Noem\State\Middleware\Mesh;

/**
 * Weave chain - AI-powered autonomous tool orchestration
 *
 * Produces a Generator that executes the complete weave workflow:
 * 1. Tool Enumeration - Discover available abilities
 * 2. AI Planning - Select appropriate tools via AI
 * 3. Tool Execution - Invoke selected abilities sequentially
 * 4. Result Aggregation - Synthesize final result via AI
 *
 * Extensible via middleware for custom planning strategies, execution policies, etc.
 *
 * @template-extends Chain<WeaveParams, \Generator>
 */
class Weave extends Chain
{
    public function __construct(
        private readonly InvokeAbility $invokeAbility,
        private readonly Mesh $aiBackends,
        private readonly AbilityRegistry $registry,
        private readonly ?WeaveConfig $weaveConfig = null,
    ) {
        parent::__construct($this->weave(...));
    }

    /**
     * Core weave provider - produces the Generator
     *
     * @param WeaveParams $params Weave parameters (intent, options, region)
     * @return \Generator Yields during async operations, returns result array
     */
    private function weave(WeaveParams $params): \Generator
    {
        // Initialize result structure
        $result = [
            'selectedTools' => [],
            'toolCalls' => [],
            'result' => null,
            'iterations' => [],
            'reasoning' => '',
        ];

        try {
            // Get maxIterations from options, falling back to config, then default
            $maxIterations = $params->options['maxIterations']
                ?? $this->weaveConfig?->getMaxIterations()
                ?? 3;

            // Step 1: Enumerate available tools (once, reused across iterations)
            $tools = yield from $this->enumerateTools($params);

            if (empty($tools)) {
                $result['reasoning'] = 'No tools available for the given intent';
                return $result;
            }

            $allToolCalls = [];
            $iterationNumber = 0;

            // Multi-iteration loop
            while ($iterationNumber < $maxIterations) {
                $iterationNumber++;

                // Step 2: AI-powered planning (initial or continuation)
                if ($iterationNumber === 1) {
                    $plan = yield from $this->planToolSelection($params, $tools);
                } else {
                    $plan = yield from $this->planContinuation($params, $tools, $allToolCalls);
                }

                $iterationReasoning = $plan['reasoning'] ?? '';

                // Validate selected tools
                $selectedTools = $this->validateSelectedTools($plan['tools'] ?? [], $tools);

                // If no tools selected, stop iteration
                if (empty($selectedTools)) {
                    if ($iterationNumber === 1) {
                        $result['reasoning'] = $iterationReasoning ?: 'No valid tools selected';
                    }
                    break;
                }

                // Store first iteration reasoning as overall reasoning
                if ($iterationNumber === 1) {
                    $result['reasoning'] = $iterationReasoning;
                    $result['selectedTools'] = array_column($selectedTools, 'ability');
                }

                // Step 3: Execute tools sequentially
                $toolCalls = yield from $this->executeTools($params, $selectedTools);

                // Accumulate tool calls across iterations
                $allToolCalls = array_merge($allToolCalls, $toolCalls);

                // Log this iteration
                $result['iterations'][] = [
                    'iteration' => $iterationNumber,
                    'tools' => array_column($selectedTools, 'ability'),
                    'reasoning' => $iterationReasoning,
                    'results' => $toolCalls,
                ];
            }

            // Store all accumulated tool calls
            $result['toolCalls'] = $allToolCalls;

            // Step 4: Final aggregation after all iterations
            if (!empty($allToolCalls)) {
                $aggregated = yield from $this->aggregateResults($params, $allToolCalls);
                $result['result'] = $aggregated;
            }
        } catch (\Exception $e) {
            $result['result'] = 'Error: ' . $e->getMessage();
        }

        return $result;
    }

    /**
     * Enumerate available tools via enumerate-abilities
     */
    private function enumerateTools(WeaveParams $params): \Generator
    {
        // Invoke enumerate-abilities
        $invokeParams = new \Noem\State\Feature\Abilities\Chains\Params\InvokeAbility(
            region: $params->region,
            abilityName: 'enumerate-abilities',
            parameters: null
        );

        $message = $this->invokeAbility->call($invokeParams);

        // Wait for response
        $response = null;
        $message->then(function ($result) use (&$response) {
            $response = $result;
        });

        while ($response === null) {
            yield;
        }

        $abilities = $response['abilities'] ?? [];

        // Filter out enumerate-abilities itself to prevent meta-recursion
        $abilities = array_filter($abilities, fn($a) => $a['name'] !== 'enumerate-abilities');

        // Apply tools filter if specified
        if (isset($params->options['tools']) && is_array($params->options['tools'])) {
            $abilities = $this->filterToolsByPatterns($abilities, $params->options['tools']);
        }

        return $abilities;
    }

    /**
     * Filter tools by name patterns (exact match or wildcard)
     */
    private function filterToolsByPatterns(array $abilities, array $patterns): array
    {
        $filtered = [];
        foreach ($abilities as $ability) {
            foreach ($patterns as $pattern) {
                if ($ability['name'] === $pattern || fnmatch($pattern, $ability['name'])) {
                    $filtered[] = $ability;
                    break;
                }
            }
        }
        return $filtered;
    }

    /**
     * Use AI to select appropriate tools
     */
    private function planToolSelection(WeaveParams $params, array $tools): \Generator
    {
        // Build planning prompt
        $prompt = $this->buildPlanningPrompt($params->intent, $tools);

        // Planning schema: {reasoning: string, tools: [{ability, parameters}]}
        $schema = [
            'type' => 'object',
            'properties' => [
                'reasoning' => ['type' => 'string', 'description' => 'Explanation of tool selection'],
                'tools' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'ability' => ['type' => 'string'],
                            'parameters' => ['type' => 'object'],
                        ],
                        'required' => ['ability', 'parameters'],
                    ],
                ],
            ],
            'required' => ['reasoning', 'tools'],
        ];

        // Get backend from options, falling back to config, then default
        $backendName = $params->options['backend']
            ?? $this->weaveConfig?->getBackend()
            ?? 'anthropic';
        $backend = $this->aiBackends[$backendName] ?? $this->aiBackends['anthropic'];

        try {
            // Build request with JSON schema for structured output
            $request = (new RequestBuilder())
                ->setPrompt($prompt)
                ->setResponseFormat(
                    new ResponseFormat(
                        'json_schema',
                        [
                            'name' => 'tool_selection',
                            'schema' => $schema,
                        ]
                    )
                )
                ->build();

            // Execute AI call via Chat
            $chatGenerator = new Chat($request, true, $backend);
            $generator = $chatGenerator();

            $responseText = '';
            while ($generator->valid()) {
                $chunk = $generator->current();
                $responseText .= $chunk;
                $generator->next();
                yield;
            }

            // Parse JSON response
            $plan = json_decode($responseText, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return ['reasoning' => 'Failed to parse AI response: ' . json_last_error_msg(), 'tools' => []];
            }

            return $plan;
        } catch (\Exception $e) {
            return ['reasoning' => 'Planning failed: ' . $e->getMessage(), 'tools' => []];
        }
    }

    /**
     * Use AI to determine if additional tools needed (continuation iteration)
     */
    private function planContinuation(WeaveParams $params, array $tools, array $previousToolCalls): \Generator
    {
        // Build continuation prompt with previous results
        $prompt = $this->buildContinuationPrompt($params->intent, $tools, $previousToolCalls);

        // Same schema as initial planning
        $schema = [
            'type' => 'object',
            'properties' => [
                'reasoning' => ['type' => 'string', 'description' => 'Explanation of whether more tools needed'],
                'tools' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'ability' => ['type' => 'string'],
                            'parameters' => ['type' => 'object'],
                        ],
                        'required' => ['ability', 'parameters'],
                    ],
                ],
            ],
            'required' => ['reasoning', 'tools'],
        ];

        // Get backend from options, falling back to config, then default
        $backendName = $params->options['backend']
            ?? $this->weaveConfig?->getBackend()
            ?? 'anthropic';
        $backend = $this->aiBackends[$backendName] ?? $this->aiBackends['anthropic'];

        try {
            // Build request with JSON schema for structured output
            $request = (new RequestBuilder())
                ->setPrompt($prompt)
                ->setResponseFormat(
                    new ResponseFormat(
                        'json_schema',
                        [
                            'name' => 'continuation_decision',
                            'schema' => $schema,
                        ]
                    )
                )
                ->build();

            // Execute AI call via Chat
            $chatGenerator = new Chat($request, true, $backend);
            $generator = $chatGenerator();

            $responseText = '';
            while ($generator->valid()) {
                $chunk = $generator->current();
                $responseText .= $chunk;
                $generator->next();
                yield;
            }

            // Parse JSON response
            $plan = json_decode($responseText, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return ['reasoning' => 'Failed to parse AI continuation response: ' . json_last_error_msg(), 'tools' => []];
            }

            return $plan;
        } catch (\Exception $e) {
            return ['reasoning' => 'Continuation planning failed: ' . $e->getMessage(), 'tools' => []];
        }
    }

    /**
     * Build planning prompt with tool definitions
     */
    private function buildPlanningPrompt(string $intent, array $tools): string
    {
        $prompt = "You are an agentic system with access to the following tools:\n\n";

        foreach ($tools as $tool) {
            $prompt .= "**{$tool['name']}**\n";
            $prompt .= "Description: {$tool['description']}\n";
            $prompt .= "Parameters: " . json_encode($tool['parameterSchema'], JSON_PRETTY_PRINT) . "\n\n";
        }

        $prompt .= "User Intent: $intent\n\n";
        $prompt .= "Your task:\n";
        $prompt .= "1. Analyze the user's intent\n";
        $prompt .= "2. Select the minimal set of tools needed to fulfill the intent\n";
        $prompt .= "3. Determine the optimal order of execution\n";
        $prompt .= "4. Generate appropriate parameters for each tool call\n";
        $prompt .= "5. Provide reasoning for your selections\n";

        return $prompt;
    }

    /**
     * Build continuation prompt with previous iteration results
     */
    private function buildContinuationPrompt(string $intent, array $tools, array $previousToolCalls): string
    {
        $prompt = "You are an agentic system with access to the following tools:\n\n";

        foreach ($tools as $tool) {
            $prompt .= "**{$tool['name']}**\n";
            $prompt .= "Description: {$tool['description']}\n";
            $prompt .= "Parameters: " . json_encode($tool['parameterSchema'], JSON_PRETTY_PRINT) . "\n\n";
        }

        $prompt .= "Original Intent: $intent\n\n";
        $prompt .= "Previous iteration results:\n";
        $prompt .= json_encode($previousToolCalls, JSON_PRETTY_PRINT) . "\n\n";
        $prompt .= "Your task:\n";
        $prompt .= "1. Review the results from previous tool calls\n";
        $prompt .= "2. Determine if the original intent has been fulfilled\n";
        $prompt .= "3. If more information is needed, select additional tools to invoke\n";
        $prompt .= "4. If the intent is fulfilled, return an empty tools array\n";
        $prompt .= "5. Provide reasoning for your decision\n";

        return $prompt;
    }

    /**
     * Validate that selected tools exist in registry
     */
    private function validateSelectedTools(array $selectedTools, array $availableTools): array
    {
        $availableNames = array_column($availableTools, 'name');
        $validated = [];

        foreach ($selectedTools as $tool) {
            if (in_array($tool['ability'] ?? '', $availableNames, true)) {
                $validated[] = $tool;
            }
        }

        return $validated;
    }

    /**
     * Execute selected tools sequentially
     */
    private function executeTools(WeaveParams $params, array $selectedTools): \Generator
    {
        $toolCalls = [];

        foreach ($selectedTools as $tool) {
            $ability = $tool['ability'];
            $parameters = $tool['parameters'] ?? null;

            $toolCall = [
                'ability' => $ability,
                'parameters' => $parameters,
                'result' => null,
                'success' => false,
                'error' => null,
            ];

            try {
                // Invoke ability
                $invokeParams = new \Noem\State\Feature\Abilities\Chains\Params\InvokeAbility(
                    region: $params->region,
                    abilityName: $ability,
                    parameters: $parameters
                );

                $message = $this->invokeAbility->call($invokeParams);

                // Wait for response
                $result = null;
                $message->then(function ($response) use (&$result) {
                    $result = $response;
                });

                while ($result === null) {
                    yield;
                }

                $toolCall['result'] = $result;
                $toolCall['success'] = true;
            } catch (\Exception $e) {
                $toolCall['error'] = $e->getMessage();
                $toolCall['success'] = false;
            }

            $toolCalls[] = $toolCall;
        }

        return $toolCalls;
    }

    /**
     * Aggregate tool results into final answer
     */
    private function aggregateResults(WeaveParams $params, array $toolCalls): \Generator
    {
        // Build aggregation prompt
        $prompt = $this->buildAggregationPrompt($params->intent, $toolCalls);

        // Get backend from options, falling back to config, then default
        $backendName = $params->options['backend']
            ?? $this->weaveConfig?->getBackend()
            ?? 'anthropic';
        $backend = $this->aiBackends[$backendName] ?? $this->aiBackends['anthropic'];

        try {
            // If schema provided, use structured output (capture)
            if (isset($params->options['schema']) && is_array($params->options['schema'])) {
                $request = (new RequestBuilder())
                    ->setPrompt($prompt)
                    ->setResponseFormat(
                        new ResponseFormat(
                            'json_schema',
                            [
                                'name' => 'aggregated_result',
                                'schema' => $params->options['schema'],
                            ]
                        )
                    )
                    ->build();

                $chatGenerator = new Chat($request, true, $backend);
                $generator = $chatGenerator();

                $responseText = '';
                while ($generator->valid()) {
                    $chunk = $generator->current();
                    $responseText .= $chunk;
                    $generator->next();
                    yield;
                }

                // Parse JSON response
                $result = json_decode($responseText, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    return $toolCalls; // Fallback to raw tool calls
                }

                return $result;
            } else {
                // No schema - use text completion
                $request = (new RequestBuilder())
                    ->setPrompt($prompt)
                    ->build();

                $completionGenerator = new Completion($request, true, $backend);
                $generator = $completionGenerator();

                $responseText = '';
                while ($generator->valid()) {
                    $chunk = $generator->current();
                    $responseText .= $chunk;
                    $generator->next();
                    yield;
                }

                return $responseText;
            }
        } catch (\Exception $e) {
            // Fallback to raw tool calls on error
            return $toolCalls;
        }
    }

    /**
     * Build aggregation prompt with intent and tool results
     */
    private function buildAggregationPrompt(string $intent, array $toolCalls): string
    {
        $prompt = "Tool call results:\n\n";
        $prompt .= json_encode($toolCalls, JSON_PRETTY_PRINT) . "\n\n";
        $prompt .= "Original intent: $intent\n\n";
        $prompt .= "Synthesize these results into a final answer addressing the user's intent.";

        return $prompt;
    }
}
