<?php

declare(strict_types=1);

namespace Noem\State\Feature\Interaction\BuildStep;

use Noem\State\BuildStep;
use Noem\State\Feature\Interaction\InteractionDefinition;
use Noem\State\Feature\Interaction\InteractionRegistry;
use Noem\State\Region;
use Noem\State\RegionBuilder;

/**
 * BuildStep for declarative interaction registration during region construction
 *
 * Enables fluent interaction definition as part of region builder pipeline.
 * Interactions are registered in InteractionRegistry before region initialization,
 * making them immediately available for discovery and validation.
 *
 * Usage:
 * $builder->addBuildStep(new RegisterInteraction(
 *     id: 'deploy_confirm',
 *     definition: new InteractionDefinition(
 *         type: 'confirm',
 *         state: 'deploying',
 *         question: 'Deploy to production?',
 *         metadata: ['defaultValue' => false]
 *     )
 * ));
 */
final class RegisterInteraction implements BuildStep
{
    /**
     * @param string $id Unique identifier for the interaction
     * @param InteractionDefinition $definition Interaction contract definition
     */
    public function __construct(
        private readonly string $id,
        private readonly InteractionDefinition $definition,
    ) {
    }

    public function callback(RegionBuilder $builder, callable $next, callable $first): Region
    {
        // Build region first
        $region = $next($builder);

        // Retrieve InteractionRegistry from ChainMail
        try {
            $registry = $builder->chainMail->get(InteractionRegistry::class);
        } catch (\Noem\State\Middleware\ChainException) {
            throw new \RuntimeException(
                'RegisterInteraction BuildStep requires InteractionRegistryFeature to be loaded'
            );
        }

        // Register interaction definition
        $registry->register($this->definition);

        return $region;
    }
}
