<?php

declare(strict_types=1);

namespace MachineAgent;

/**
 * AI Helper - Simplifies common AI operations
 *
 * Provides convenient wrappers around $this->capture() and $this->complete()
 * to reduce boilerplate in inline callbacks.
 *
 * Usage in inline callbacks:
 * $ai = new AiHelper($this);
 * $analysis = $ai->captureJson($prompt, $schema);
 */
class AiHelper
{
    public function __construct(
        private readonly object $context
    ) {
    }

    /**
     * Capture AI output with JSON schema validation
     *
     * @param string $prompt The prompt to send to AI
     * @param array<string, mixed> $schema JSON schema for validation
     * @param string $backend AI backend ('ollama', 'anthropic', etc.)
     * @return array<string, mixed> Validated JSON response
     */
    public function captureJson(string $prompt, array $schema, string $backend = 'ollama'): array
    {
        return $this->context->capture($prompt, $schema, $backend);
    }

    /**
     * Simple text completion
     *
     * NOTE: $this->complete() returns a Generator, not a string.
     * This method consumes the generator and returns the complete text.
     *
     * @param string $prompt The prompt to send to AI
     * @param string $backend AI backend
     * @return string Completion text
     */
    public function complete(string $prompt, string $backend = 'ollama'): string
    {
        $generator = $this->context->complete($prompt, $backend);

        // Consume generator and collect chunks
        $result = '';
        foreach ($generator as $chunk) {
            $result .= $chunk;
        }

        return $result;
    }

    /**
     * Analyze requirements and return confidence assessment
     *
     * Common pattern: assess confidence, identify missing info, extract requirements
     *
     * @param string $userRequest Original user request
     * @param array<array{question: string, answer: string}> $qaHistory Question-answer history
     * @return array{confidence: float, complexity: string, missing_info: array<string>, extracted_requirements: array<string, mixed>}
     */
    public function analyzeRequirements(string $userRequest, array $qaHistory = []): array
    {
        // Build context from Q&A history
        $context = "Original request: {$userRequest}\n\n";
        if (!empty($qaHistory)) {
            $context .= "Additional information from Q&A:\n";
            foreach ($qaHistory as $qa) {
                $context .= "Q: {$qa['question']}\nA: {$qa['answer']}\n\n";
            }
        }

        // Define analysis schema
        $schema = [
            'type' => 'object',
            'required' => ['confidence', 'complexity', 'missing_info', 'extracted_requirements'],
            'properties' => [
                'confidence' => [
                    'type' => 'number',
                    'minimum' => 0.0,
                    'maximum' => 1.0,
                    'description' => 'Confidence in understanding requirements (0.0-1.0)',
                ],
                'complexity' => [
                    'type' => 'string',
                    'enum' => ['simple', 'moderate', 'complex'],
                    'description' => 'Estimated machine complexity',
                ],
                'missing_info' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'List of missing or unclear requirements',
                ],
                'extracted_requirements' => [
                    'type' => 'object',
                    'description' => 'Structured requirements extracted from request',
                ],
            ],
        ];

        $prompt = <<<PROMPT
Analyze this state machine request:

{$context}

Assess:
1. Confidence in understanding what's needed (0.0-1.0)
2. Complexity (simple/moderate/complex)
3. Missing or unclear information
4. Extract structured requirements (states, features, behavior)
PROMPT;

        return $this->captureJson($prompt, $schema, 'ollama');
    }

    /**
     * Generate a clarifying question based on missing information
     *
     * @param array<string> $missingInfo List of missing requirements
     * @return string Focused clarifying question
     */
    public function generateQuestion(array $missingInfo): string
    {
        $items = implode("\n", $missingInfo);
        $prompt = <<<PROMPT
Based on these missing requirements:
{$items}

Ask ONE focused clarifying question.
PROMPT;

        return $this->complete($prompt, 'ollama');
    }

    /**
     * Create implementation plan from requirements
     *
     * @param array<string, mixed> $requirements Extracted requirements
     * @param array<string, mixed> $holonSpec Holon specification
     * @param array<string, mixed> $examples Example machines
     * @return array{machine_name: string, states: array, features: array, context_variables: array, abilities_needed: array}
     */
    public function createPlan(array $requirements, array $holonSpec, array $examples): array
    {
        $requirementsJson = json_encode($requirements, JSON_PRETTY_PRINT);
        $holonSpecYaml = yaml_emit($holonSpec);
        $examplesJson = json_encode($examples, JSON_PRETTY_PRINT);

        // Define planning schema
        $planSchema = [
            'type' => 'object',
            'required' => ['machine_name', 'states', 'features', 'context_variables'],
            'properties' => [
                'machine_name' => ['type' => 'string', 'pattern' => '^[a-z][a-z0-9-]*$'],
                'states' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'purpose' => ['type' => 'string'],
                            'transitions' => ['type' => 'array'],
                        ],
                    ],
                ],
                'features' => ['type' => 'array', 'items' => ['type' => 'string']],
                'context_variables' => ['type' => 'object'],
                'abilities_needed' => ['type' => 'array', 'items' => ['type' => 'object']],
            ],
        ];

        $prompt = <<<PROMPT
Create implementation plan:

Requirements: {$requirementsJson}

Holon Spec:
{$holonSpecYaml}

Examples:
{$examplesJson}

Provide:
1. Machine name (kebab-case)
2. States (name, purpose, transitions)
3. Required features (full class names)
4. Context variable schema
5. Abilities to expose (if any)
PROMPT;

        return $this->captureJson($prompt, $planSchema, 'ollama');
    }
}
