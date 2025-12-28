<?php

declare(strict_types=1);

namespace Noem\State\Feature\Ai\Chains;

use Noem\State\Middleware\Chain;

/**
 * Provider-specific prompt template resolution via chain
 *
 * Implements resolution hierarchy: model → provider → base
 * Enables middleware-based template customization per backend.
 */
class PromptTemplate extends Chain
{
    /**
     * @var array<string, array<string, string>> Provider and model-specific templates
     */
    private array $templates = [];

    /**
     * @var array<string, string> Base templates by type
     */
    private array $baseTemplates = [
        'completion' => '',
        'chat' => '',
        'system' => '',
    ];

    public function __construct()
    {
        parent::__construct(
            provider: fn(array $context) => $context
        );
    }

    /**
     * Resolve template for backend and type
     *
     * Resolution order:
     * 1. Model-specific template (e.g., $templates['openai']['gpt-4'])
     * 2. Provider template (e.g., $templates['openai']['completion'])
     * 3. Base template (e.g., $baseTemplates['completion'])
     *
     * @param string $backend Backend name ('openai', 'ollama', 'anthropic')
     * @param string $type Template type ('completion', 'chat', 'system')
     * @return string Resolved template string
     */
    public function __invoke(string $backend, string $type): string
    {
        // Try model-specific template
        if (isset($this->templates[$backend][$type])) {
            $template = $this->templates[$backend][$type];
        } else {
            // Fallback to base template
            $template = $this->baseTemplates[$type] ?? '';
        }

        // Allow middleware to transform template
        return $this->call(['backend' => $backend, 'type' => $type, 'template' => $template])['template'] ?? $template;
    }

    /**
     * Register provider-specific template
     *
     * @param string $backend Backend name
     * @param string $type Template type
     * @param string $template Template content
     * @return void
     */
    public function registerTemplate(string $backend, string $type, string $template): void
    {
        $this->templates[$backend][$type] = $template;
    }

    /**
     * Register base template
     *
     * @param string $type Template type
     * @param string $template Template content
     * @return void
     */
    public function registerBaseTemplate(string $type, string $template): void
    {
        $this->baseTemplates[$type] = $template;
    }
}
