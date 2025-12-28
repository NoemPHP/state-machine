<?php

declare(strict_types=1);

namespace Noem\State\Feature\Agentic;

/**
 * Configuration object for Weave chain defaults
 *
 * Supplied via ChainMail by AiConfigFeature when weave configuration is provided.
 * Enables declarative configuration of weave behavior without mixing concerns
 * between AiFeature and AgenticFeature.
 */
class WeaveConfig
{
    /**
     * @param int|null $defaultMaxIterations Default iteration limit (null = 3)
     * @param string|null $defaultBackend Default AI backend (null = 'anthropic')
     * @param string|null $defaultComplexity Default complexity level for AI calls
     * @param array<string, string> $promptTemplates Custom prompt templates (planning, aggregation, continuation)
     */
    public function __construct(
        public readonly ?int $defaultMaxIterations = null,
        public readonly ?string $defaultBackend = null,
        public readonly ?string $defaultComplexity = null,
        public readonly array $promptTemplates = [],
    ) {
    }

    /**
     * Get max iterations with fallback to default
     */
    public function getMaxIterations(): int
    {
        return $this->defaultMaxIterations ?? 3;
    }

    /**
     * Get backend with fallback to default
     */
    public function getBackend(): string
    {
        return $this->defaultBackend ?? 'anthropic';
    }

    /**
     * Get complexity with fallback
     */
    public function getComplexity(): ?string
    {
        return $this->defaultComplexity;
    }

    /**
     * Get planning template if configured
     */
    public function getPlanningTemplate(): ?string
    {
        return $this->promptTemplates['planning'] ?? null;
    }

    /**
     * Get aggregation template if configured
     */
    public function getAggregationTemplate(): ?string
    {
        return $this->promptTemplates['aggregation'] ?? null;
    }

    /**
     * Get continuation template if configured
     */
    public function getContinuationTemplate(): ?string
    {
        return $this->promptTemplates['continuation'] ?? null;
    }
}
