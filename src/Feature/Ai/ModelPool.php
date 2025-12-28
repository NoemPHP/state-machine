<?php

declare(strict_types=1);

namespace Noem\State\Feature\Ai;

/**
 * Model capability pool for declarative model selection
 */
class ModelPool
{
    /** @var array<string, mixed> */
    private array $models = [];

    private int $defaultComplexity = 2;
    private int $defaultContext = 8;

    /** @var array<string> */
    private array $preferProviders = [];

    private string $costBias = 'moderate';

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [])
    {
        if (isset($config['models'])) {
            foreach ($config['models'] as $model) {
                $this->addModel(
                    $model['id'],
                    $model['provider'],
                    $model['model'],
                    $model['complexity'],
                    $model['context'],
                    $model['cost'] ?? 'medium'
                );
            }
        }

        if (isset($config['preferences'])) {
            $prefs = $config['preferences'];
            $this->defaultComplexity = $prefs['defaultComplexity'] ?? 2;
            $this->defaultContext = $prefs['defaultContext'] ?? 8;
            $this->preferProviders = $prefs['preferProviders'] ?? [];
            $this->costBias = $prefs['costBias'] ?? 'moderate';
        }
    }

    public function addModel(
        string $id,
        string $provider,
        string $model,
        int $complexity,
        int $context,
        string $cost
    ): void {
        $this->models[$id] = [
            'id' => $id,
            'provider' => $provider,
            'model' => $model,
            'complexity' => $complexity,
            'context' => $context,
            'cost' => $cost,
        ];
    }

    public function selectModel(?int $complexity = null, ?int $context = null): ?array
    {
        $reqComplexity = $complexity ?? $this->defaultComplexity;
        $reqContext = $context ?? $this->defaultContext;

        $candidates = [];

        foreach ($this->models as $model) {
            if ($model['complexity'] >= $reqComplexity && $model['context'] >= $reqContext) {
                $candidates[] = $model;
            }
        }

        if (empty($candidates)) {
            return null;
        }

        // Score and sort candidates
        usort($candidates, function ($a, $b) use ($reqComplexity, $reqContext) {
            $scoreA = abs($a['complexity'] - $reqComplexity) * 10 + abs($a['context'] - $reqContext);
            $scoreB = abs($b['complexity'] - $reqComplexity) * 10 + abs($b['context'] - $reqContext);

            return $scoreA <=> $scoreB;
        });

        return $candidates[0];
    }

    public function getDefaultComplexity(): int
    {
        return $this->defaultComplexity;
    }

    public function getDefaultContext(): int
    {
        return $this->defaultContext;
    }
}
