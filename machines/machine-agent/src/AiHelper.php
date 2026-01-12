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
     * @throws \RuntimeException If capture fails or returns invalid data
     */
    public function captureJson(string $prompt, array $schema, string $backend = 'ollama'): array
    {
        try {
            $result = $this->context->capture($prompt, $schema, $backend);

            if ($result === null) {
                throw new \RuntimeException(
                    "AI capture returned null. This usually means:\n" .
                    "1. The AI backend is not running or unreachable\n" .
                    "2. JSON schema validation failed\n" .
                    "3. The AI response was invalid or empty\n\n" .
                    "Backend: {$backend}\n" .
                    "Check your AI backend configuration and ensure it's running."
                );
            }

            if (!is_array($result)) {
                throw new \RuntimeException(
                    "AI capture returned unexpected type: " . gettype($result) . "\n" .
                    "Expected array, got: " . var_export($result, true)
                );
            }

            return $result;
        } catch (\Exception $e) {
            throw new \RuntimeException(
                "AI capture failed: " . $e->getMessage() . "\n" .
                "Backend: {$backend}\n" .
                "Prompt preview: " . substr($prompt, 0, 200) . "...",
                0,
                $e
            );
        }
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
Analyze this state machine request critically:

{$context}

CRITICAL ASSESSMENT CRITERIA:
You are assessing whether we have SUFFICIENT IMPLEMENTATION DETAILS, not just conceptual understanding.

For state machines, we need:
- Clear workflow/state transitions (e.g., "todo -> in_progress -> done")
- Specific features/capabilities (e.g., "validate email addresses", "persist to database")
- Data requirements (what context variables are needed?)
- Trigger conditions (what causes state changes?)
- Actions to perform (what happens in each state?)

CONFIDENCE SCORING:
- 0.9-1.0: Complete implementation details provided (states, transitions, data, features explicitly listed)
- 0.7-0.8: Good overview but missing some implementation details
- 0.5-0.6: Concept is clear but most implementation details missing
- 0.3-0.4: Vague request, very few implementation details
- 0.0-0.2: Unclear or nonsensical request

EXAMPLES:
❌ Low confidence (0.3): "a wedding planner" - concept clear, but no states, data, or workflow specified
❌ Low confidence (0.4): "task manager" - too generic, missing workflow details
✅ Medium confidence (0.6): "task manager with todo/doing/done states and priority levels"
✅ High confidence (0.8): "task manager: idle->creating->active->completed states, store tasks with priority/title/description, validate non-empty titles"

Analyze the request and be STRICT. If implementation details are missing, report low confidence and identify what's needed.

Return:
1. Confidence score (0.0-1.0, be critical!)
2. Complexity (simple/moderate/complex)
3. Missing information (be specific about what implementation details are unclear)
4. Extracted requirements (only what was explicitly stated)
PROMPT;

        return $this->captureJson($prompt, $schema, 'ollama');
    }

    /**
     * Generate a clarifying question based on missing information
     *
     * Returns either:
     * - array with 'type' => 'select', 'question' => string, 'options' => array (for multiple choice)
     * - array with 'type' => 'choice', 'question' => string, 'options' => array (for multi-select)
     * - array with 'type' => 'prompt', 'question' => string (for free-form)
     *
     * @param array<string> $missingInfo List of missing requirements
     * @param int $questionCount Number of questions asked so far (0-based)
     * @return array{type: string, question: string, options?: array<string, string>}
     */
    public function generateQuestion(array $missingInfo, int $questionCount = 0): array
    {
        $items = implode("\n", $missingInfo);

        $schema = [
            'type' => 'object',
            'required' => ['interaction_type', 'question'],
            'properties' => [
                'interaction_type' => [
                    'type' => 'string',
                    'enum' => ['prompt', 'select', 'choice'],
                    'description' => 'Type of interaction - select for single choice, choice for multi-select, prompt for free-form',
                ],
                'question' => [
                    'type' => 'string',
                    'description' => 'The clarifying question to ask',
                ],
                'options' => [
                    'type' => 'object',
                    'description' => 'Options for select/choice (key => label). Omit for prompt type.',
                ],
            ],
        ];

        // Determine question priority level based on question count
        $priorityGuidance = match (true) {
            $questionCount === 0 => "FIRST QUESTION - Ask about DELIVERABLE TYPE (what kind of system are they building)",
            $questionCount === 1 => "SECOND QUESTION - Ask about PURPOSE and core functionality",
            $questionCount === 2 => "THIRD QUESTION - Ask about user-facing operations or capabilities",
            default => "LATER QUESTION - Ask about implementation details or specific features"
        };

        $prompt = <<<PROMPT
Based on these missing requirements:
{$items}

Question #{$questionCount} - {$priorityGuidance}

CRITICAL: Ask HIGH-LEVEL questions, NOT technical implementation details.

QUESTION PRIORITY (ask in this order):
1. **Deliverable Type** - What kind of system? (CLI tool, web app, API, agent-to-agent, batch processor, desktop app, service, etc.)
2. **Purpose & Functionality** - What does it do? What problem does it solve?
3. **User Operations** - What can users do with it? What are the main capabilities?
4. **Data & Resources** - What data does it work with? What external services?
5. **Features & Requirements** - Specific features needed (persistence, validation, async, etc.)

AVOID asking about:
- State transitions or workflow patterns (too technical)
- Internal implementation details
- State management concepts
- Technical architecture decisions

Choose the best interaction type:
- 'select': When asking user to pick ONE option from a list
- 'choice': When user should select MULTIPLE options
- 'prompt': When you need free-form text input

GOOD EXAMPLES (high-level, user-focused):
{
  "interaction_type": "select",
  "question": "What type of application are you building?",
  "options": {
    "cli": "Command-line tool (terminal-based, automation scripts)",
    "web": "Web application (browser-based, HTTP requests/responses)",
    "api": "API service (REST/GraphQL endpoints for other systems)",
    "agent": "Agent-to-agent system (machines communicating with each other)",
    "batch": "Batch processor (scheduled jobs, queue processing)",
    "desktop": "Desktop application (GUI, local system)"
  }
}

{
  "interaction_type": "prompt",
  "question": "What is the main purpose of this application? What problem does it solve?"
}

{
  "interaction_type": "choice",
  "question": "What are the main capabilities users need? (Select all that apply)",
  "options": {
    "create": "Create/add new items or records",
    "read": "View/search/filter existing data",
    "update": "Modify/edit existing items",
    "delete": "Remove items or records",
    "process": "Transform or process data",
    "notify": "Send notifications or alerts"
  }
}

BAD EXAMPLES (too technical):
❌ "What type of workflow pattern?" - Too technical, users don't think in these terms
❌ "How should state transitions work?" - Implementation detail
❌ "Which middleware should be used?" - Internal architecture
PROMPT;

        return $this->captureJson($prompt, $schema, 'ollama');
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
