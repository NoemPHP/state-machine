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
     * Stream AI completion via template and collect result
     *
     * Common pattern: Stream template with {{#complete}}, collect chunks
     *
     * @param string $prompt Prompt to send to AI
     * @param string $backend AI backend
     * @param int $maxTokens Maximum tokens to generate
     * @param bool $cleanMarkdown Remove markdown code block wrapping
     * @return Generator<int, void, mixed, string> Generator yielding result string
     */
    public function streamComplete(
        string $prompt,
        string $backend = 'ollama',
        int $maxTokens = 4000,
        bool $cleanMarkdown = true
    ): Generator {
        // Set prompt in context
        $this->context->set('_tpl_prompt', $prompt);

        // Create template
        $template = $this->context->template(
            "{{#complete max={$maxTokens} backend=\"{$backend}\"}}{{_tpl_prompt}}{{/complete}}"
        );
        yield;

        // Collect chunks
        $result = '';
        while ($template->valid()) {
            $chunk = $template->current();
            $result .= $chunk;
            $template->next();
            yield;
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
     * Stream AI capture (with JSON schema) via template and collect result
     *
     * @param string $prompt Prompt to send to AI
     * @param array<string, mixed> $schema JSON schema for validation
     * @param string $backend AI backend
     * @param int $maxTokens Maximum tokens to generate
     * @return Generator<int, void, mixed, array<string, mixed>> Generator yielding validated JSON
     */
    public function streamCapture(
        string $prompt,
        array $schema,
        string $backend = 'ollama',
        int $maxTokens = 4000
    ): Generator {
        // Set prompt in context
        $this->context->set('_tpl_prompt', $prompt);
        $this->context->set('_tpl_schema', $schema);

        // Create template
        $schemaJson = json_encode($this->context->get('_tpl_schema'));
        $template = $this->context->template(
            "{{#capture max={$maxTokens} backend=\"{$backend}\" schema=_tpl_schema}}{{_tpl_prompt}}{{/capture}}"
        );
        yield;

        // Collect result
        $result = null;
        while ($template->valid()) {
            $result = $template->current();
            $template->next();
            yield;
        }

        return $result ?? [];
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
