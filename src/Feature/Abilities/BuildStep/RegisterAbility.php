<?php

declare(strict_types=1);

namespace Noem\State\Feature\Abilities\BuildStep;

use Noem\State\BuildStep;
use Noem\State\Feature\Abilities\AbilityDefinition;
use Noem\State\Feature\Abilities\AbilityRegistry;
use Noem\State\Region;
use Noem\State\RegionBuilder;

/**
 * BuildStep for declarative ability registration during region construction
 *
 * Enables fluent ability definition as part of region builder pipeline.
 * Abilities are registered in AbilityRegistry before region initialization,
 * making them immediately available for invocation.
 */
final class RegisterAbility implements BuildStep
{
    /**
     * @param string $name Unique ability identifier
     * @param callable $handler Execution logic receiving parameters and returning response data
     * @param callable|null $predicate Optional conditional exposure callable receiving Region, returning bool
     * @param string $description Human-readable documentation (default: empty)
     * @param array $parameterSchema JSON Schema for parameter validation (default: empty = no validation)
     * @param array $responseSchema JSON Schema for response validation (default: empty)
     */
    public function __construct(
        private readonly string $name,
        private readonly mixed $handler,
        private readonly mixed $predicate = null,
        private readonly string $description = '',
        private readonly array $parameterSchema = [],
        private readonly array $responseSchema = [],
    ) {
        if (!is_callable($handler)) {
            throw new \InvalidArgumentException('Handler must be callable');
        }

        if ($predicate !== null && !is_callable($predicate)) {
            throw new \InvalidArgumentException('Predicate must be callable or null');
        }
    }

    public function callback(RegionBuilder $builder, callable $next, callable $first): Region
    {
        // Build region first
        $region = $next($builder);

        // Retrieve AbilityRegistry from ChainMail
        $registry = $builder->chainMail->get(AbilityRegistry::class);

        // Create and register ability definition
        $definition = new AbilityDefinition(
            name: $this->name,
            description: $this->description,
            parameterSchema: $this->parameterSchema,
            responseSchema: $this->responseSchema,
            handler: $this->handler,
            predicate: $this->predicate,
        );

        $registry->register($definition);

        return $region;
    }
}
