<?php

declare(strict_types=1);

namespace Noem\State\Feature\Ai;

use Noem\State\Feature\Agentic\WeaveConfig;
use Noem\State\Feature\Ai\Backend\AnthropicBackend;
use Noem\State\Feature\Ai\Backend\OllamaBackend;
use Noem\State\Feature\Ai\Backend\OpenAiBackend;
use Noem\State\Feature\Feature;
use Noem\State\Middleware\ChainMail;
use Noem\State\Middleware\Mesh;

/**
 * Optional AiConfigFeature for runtime credential injection and ModelPool configuration
 *
 * Extends backend mesh via offsetGet chain to inject credentials
 * when backends are accessed, enabling runtime credential resolution
 * without premature backend instantiation.
 *
 * Additionally manages ModelPool for capability-based model selection.
 * Supplies WeaveConfig when weave configuration is provided.
 */
class AiConfigFeature implements Feature
{
    private ?ModelPool $modelPool = null;
    private ?WeaveConfig $weaveConfig = null;

    /**
     * @param array<string, mixed> $config Configuration array supporting:
     *   - credentials: Backend credentials (apiKey, baseUrl)
     *   - modelPool: Array of model definitions
     *   - preferences: Selection preferences (defaultComplexity, defaultContext, etc)
     *   - weave: Weave configuration (defaultMaxIterations, defaultBackend, defaultComplexity, promptTemplates)
     */
    public function __construct(
        private readonly array $config = []
    ) {
        // Build ModelPool if configuration provided
        if (isset($config['modelPool'])) {
            $this->modelPool = new ModelPool([
                'models' => $config['modelPool'],
                'preferences' => $config['preferences'] ?? [],
            ]);
        }

        // Build WeaveConfig if weave configuration provided
        if (isset($config['weave'])) {
            $weaveConfigData = $config['weave'];
            $this->weaveConfig = new WeaveConfig(
                defaultMaxIterations: $weaveConfigData['defaultMaxIterations'] ?? null,
                defaultBackend: $weaveConfigData['defaultBackend'] ?? null,
                defaultComplexity: $weaveConfigData['defaultComplexity'] ?? null,
                promptTemplates: $weaveConfigData['promptTemplates'] ?? [],
            );
        }
    }

    public function __invoke(ChainMail $chainMail): void
    {
        // Supply ModelPool if available
        if ($this->modelPool !== null) {
            $chainMail->supply(fn(): ModelPool => $this->modelPool);
        }

        // Supply WeaveConfig if available
        if ($this->weaveConfig !== null) {
            $chainMail->supply(fn(): WeaveConfig => $this->weaveConfig);
        }

        $chainMail->use(function (Mesh $backends): void {
            // Extend backend mesh to inject credentials at access time
            $backends->extend(
                offsetGet: function (mixed $offset, callable $next) {
                    $backend = $next($offset);

                    // Get credentials from config structure
                    $credentials = $this->config['credentials'] ?? [];

                    // If no credentials configured for this backend, return unchanged
                    if (!isset($credentials[$offset])) {
                        return $backend;
                    }

                    $config = $credentials[$offset];

                    // Create new backend instance with injected credentials
                    return match (get_class($backend)) {
                        OpenAiBackend::class => new OpenAiBackend(
                            apiKey: $config['apiKey'] ?? null,
                            baseUrl: $config['baseUrl'] ?? null
                        ),
                        OllamaBackend::class => new OllamaBackend(
                            baseUrl: $config['baseUrl'] ?? null
                        ),
                        AnthropicBackend::class => new AnthropicBackend(
                            apiKey: $config['apiKey'] ?? null,
                            baseUrl: $config['baseUrl'] ?? null
                        ),
                        default => $backend
                    };
                }
            );
        });
    }
}
