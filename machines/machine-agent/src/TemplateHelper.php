<?php

declare(strict_types=1);

namespace MachineAgent;

use Generator;

/**
 * Template Helper - Simplifies template streaming operations
 *
 * Provides convenient wrappers around $this->template() to reduce
 * boilerplate for common streaming patterns.
 *
 * Usage in inline callbacks:
 * $tpl = new TemplateHelper($this);
 * $yaml = yield from $tpl->streamComplete($prompt, $backend);
 */
class TemplateHelper
{
    public function __construct(
        private readonly object $context
    ) {
    }

    /**
     * Stream AI completion and collect result
     *
     * Uses $this->complete() which returns a Generator that yields chunks.
     * This method consumes the generator with yielding for async scheduler.
     *
     * @param string $prompt Prompt to send to AI
     * @param string $backend AI backend
     * @param int $maxTokens Maximum tokens to generate (NOTE: not supported by complete() yet)
     * @param bool $cleanMarkdown Remove markdown code block wrapping
     * @return Generator<int, void, mixed, string> Generator yielding result string
     */
    public function streamComplete(
        string $prompt,
        string $backend = 'ollama',
        int $maxTokens = 4000,
        bool $cleanMarkdown = true
    ): Generator {
        // Get generator from complete()
        $generator = $this->context->complete($prompt, $backend);
        yield;

        // Collect chunks
        $result = '';
        foreach ($generator as $chunk) {
            $result .= $chunk;
            yield;  // Yield for async scheduler
        }

        // Clean markdown wrapping
        if ($cleanMarkdown) {
            $result = preg_replace('/^```ya?ml\n/', '', $result);
            $result = preg_replace('/^```php\n/', '', $result);
            $result = preg_replace('/\n```$/', '', trim($result));
        }

        return $result;
    }

    /**
     * AI capture with JSON schema validation
     *
     * Uses $this->capture() which returns validated JSON directly.
     * This method yields for async scheduler compatibility.
     *
     * @param string $prompt Prompt to send to AI
     * @param array<string, mixed> $schema JSON schema for validation
     * @param string $backend AI backend
     * @param int $maxTokens Maximum tokens to generate (NOTE: not supported by capture() yet)
     * @return Generator<int, void, mixed, array<string, mixed>> Generator yielding validated JSON
     */
    public function streamCapture(
        string $prompt,
        array $schema,
        string $backend = 'ollama',
        int $maxTokens = 4000
    ): Generator {
        // capture() returns validated JSON directly (not a generator)
        $result = $this->context->capture($prompt, $schema, $backend);
        yield;

        return $result;
    }

    /**
     * Generate YAML with inline or separate functions
     *
     * @param string $machineName Machine name
     * @param array<array{name: string, purpose: string, transitions: array}> $stateFlow States
     * @param array<string> $requiredFeatures Feature class names
     * @param array<string, mixed> $requirements Extracted requirements
     * @param array<string, mixed> $examples Example machines
     * @param bool $useInline Use inline functions (true) or separate file (false)
     * @return Generator<int, void, mixed, string> Generator yielding YAML content
     */
    public function generateYaml(
        string $machineName,
        array $stateFlow,
        array $requiredFeatures,
        array $requirements,
        array $examples,
        bool $useInline
    ): Generator {
        $stateFlowJson = json_encode($stateFlow, JSON_PRETTY_PRINT);
        $requiredFeaturesJson = json_encode($requiredFeatures, JSON_PRETTY_PRINT);
        $requirementsJson = json_encode($requirements, JSON_PRETTY_PRINT);
        $examplesJson = json_encode($examples, JSON_PRETTY_PRINT);

        // Build appropriate prompt
        if ($useInline) {
            $prompt = <<<PROMPT
Generate Holon YAML with INLINE functions.

Machine: {$machineName}
States: {$stateFlowJson}
Features: {$requiredFeaturesJson}
Requirements: {$requirementsJson}
Examples: {$examplesJson}

CRITICAL:
1. Output valid YAML for Holon::fromYaml()
2. NO machine.require (inline functions only)
3. ALWAYS add '# language=injectablephp' comment before EACH !php tag
4. Use: !php return function(object \$t) { ... };
5. Multiline use: !php | with proper YAML literal block syntax
6. Guards return bool: !php return function(object \$t): bool { return \$this->get('ready') === true; };
7. Keep functions simple (1-2 lines)
8. Set eventLoop.maxIterations appropriately
9. Features in correct order (ExtendedState FIRST)

Return ONLY valid YAML, no markdown.
PROMPT;
        } else {
            $prompt = <<<PROMPT
Generate Holon YAML with SEPARATE functions file.

Machine: {$machineName}
States: {$stateFlowJson}
Features: {$requiredFeaturesJson}
Requirements: {$requirementsJson}
Examples: {$examplesJson}

CRITICAL:
1. Output valid YAML for Holon::fromYaml()
2. Include machine.require: !php require '/var/www/html/machines/{$machineName}/holon-functions.php'
3. Use function references: !php return functionName()
4. Set eventLoop.maxIterations appropriately
5. Features in correct order (ExtendedState FIRST)

Return ONLY valid YAML, no markdown.
PROMPT;
        }

        return yield from $this->streamComplete($prompt, 'ollama', 4000, true);
    }

    /**
     * Generate PHP functions file
     *
     * @param string $machineName Machine name
     * @param array<array{name: string, purpose: string, transitions: array}> $stateFlow States
     * @param array<string, mixed> $contextVariables Context schema
     * @param array<string, mixed> $requirements Extracted requirements
     * @param array<array{name: string, handler: string}> $abilitiesNeeded Abilities to implement
     * @return Generator<int, void, mixed, string> Generator yielding PHP content
     */
    public function generatePhp(
        string $machineName,
        array $stateFlow,
        array $contextVariables,
        array $requirements,
        array $abilitiesNeeded
    ): Generator {
        $stateFlowJson = json_encode($stateFlow, JSON_PRETTY_PRINT);
        $contextVariablesJson = json_encode($contextVariables, JSON_PRETTY_PRINT);
        $requirementsJson = json_encode($requirements, JSON_PRETTY_PRINT);
        $abilitiesNeededJson = json_encode($abilitiesNeeded, JSON_PRETTY_PRINT);

        $prompt = <<<PROMPT
Generate PHP functions file.

Machine: {$machineName}
States: {$stateFlowJson}
Context: {$contextVariablesJson}
Requirements: {$requirementsJson}
Abilities: {$abilitiesNeededJson}

Pattern:
function onEnterState() {
    return function(object \$t): void {
        // Use \$this->get/set
    };
}

CRITICAL:
1. <?php declare(strict_types=1);
2. Module-level functions
3. Type hints: object \$t (catch-all) or specific types (filtered)
4. Return types: void (onEnter/onExit), Generator (async), bool (guards)
5. Guards MUST return bool (=== true/false)
6. capture/complete return values directly (NOT yield from)
7. Async MUST yield at least once

Return ONLY valid PHP, no markdown.
PROMPT;

        return yield from $this->streamComplete($prompt, 'ollama', 8000, true);
    }
}
