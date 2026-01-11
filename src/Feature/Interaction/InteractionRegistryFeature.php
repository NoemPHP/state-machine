<?php

declare(strict_types=1);

namespace Noem\State\Feature\Interaction;

use Noem\State\Chains\EnhanceRegionBuilder;
use Noem\State\Feature\Abilities\AbilityDefinition;
use Noem\State\Feature\Abilities\AbilityRegistry;
use Noem\State\Feature\ExtendedState\ContextChains\BoundAccess;
use Noem\State\Feature\ExtendedState\ContextChains\Params\BoundAccessParams;
use Noem\State\Feature\Feature;
use Noem\State\Feature\Loader\LoaderChains\Schema;
use Noem\State\Feature\RequiresFeature;
use Noem\State\Middleware\ChainMail;
use Noem\State\Region;
use Noem\State\RegionBuilder;

/**
 * Interaction registry and discovery feature
 *
 * Extends InteractionFeature with declarative interaction contract registration,
 * discovery abilities, and validation infrastructure for external agent introspection.
 *
 * Provides:
 * - InteractionRegistry for interaction definition storage
 * - Discovery abilities (enumerate-interactions, get-interaction, get-interactions-for-state)
 * - BoundAccess $this->registerInteraction() API
 * - RegionBuilder ->registerInteraction() API
 * - YAML schema extension for interactions blocks
 * - Optional contract validation and tracking
 *
 * Dependencies:
 * - InteractionFeature (required): Provides base interaction infrastructure
 * - AbilitiesFeature (optional): Enables discovery abilities
 * - ExtendedState (optional): Enables BoundAccess $this->registerInteraction()
 * - RegionLoader (optional): Enables YAML interactions blocks
 */
#[RequiresFeature(InteractionFeature::class)]
class InteractionRegistryFeature implements Feature
{
    #[\Override]
    public function __invoke(ChainMail $chainMail): void
    {
        // Register InteractionRegistry in ChainMail
        $registry = new InteractionRegistry();
        $chainMail->supply(fn(): InteractionRegistry => $registry);

        // Wire registerInteraction() into BoundAccess if ExtendedState is loaded
        $chainMail->use(
            function (?BoundAccess $boundAccess = null) use ($registry) {
                if ($boundAccess !== null) {
                    $this->wireBoundAccess($boundAccess, $registry);
                }
            }
        );

        // Wire registerInteraction() into RegionBuilder
        $chainMail->use(
            function (?EnhanceRegionBuilder $enhanceBuilder = null) use ($registry) {
                if ($enhanceBuilder !== null) {
                    $this->wireRegionBuilder($enhanceBuilder, $registry);
                }
            }
        );

        // Register discovery abilities if AbilitiesFeature is loaded
        $chainMail->use(
            function (?AbilityRegistry $abilityRegistry = null) use ($registry) {
                if ($abilityRegistry !== null) {
                    $this->registerDiscoveryAbilities($abilityRegistry, $registry);
                }
            }
        );

        // Extend YAML schema if RegionLoader is loaded
        $chainMail->use(
            function (?Schema $schema = null) {
                if ($schema !== null) {
                    $this->extendYamlSchema($schema);
                }
            }
        );

        // Process YAML interactions if RegionLoader is loaded
        $chainMail->use(
            function (?EnhanceRegionBuilder $enhanceBuilder = null) use ($registry) {
                if ($enhanceBuilder !== null) {
                    $this->processYamlInteractions($enhanceBuilder, $registry);
                }
            }
        );
    }

    /**
     * Wire registerInteraction() into BoundAccess for runtime registration
     */
    private function wireBoundAccess(BoundAccess $boundAccess, InteractionRegistry $registry): void
    {
        $boundAccess->link(function (BoundAccessParams $params, callable $next) use ($registry) {
            if ($params->type !== BoundAccessParams::TYPE_METHOD || $params->name !== 'registerInteraction') {
                return $next($params);
            }

            // Extract arguments: registerInteraction($id, InteractionDefinition $definition)
            $args = $params->payload ?? [];
            $id = $args[0] ?? null;
            $definition = $args[1] ?? null;

            if (!is_string($id)) {
                throw new \InvalidArgumentException(
                    'registerInteraction() requires string id as first parameter'
                );
            }

            if (!$definition instanceof InteractionDefinition) {
                throw new \InvalidArgumentException(
                    'registerInteraction() requires InteractionDefinition as second parameter'
                );
            }

            // Register in registry
            $registry->register($definition);

            return null;
        }, prepend: true);
    }

    /**
     * Process YAML interactions config and register BuildSteps
     */
    private function wireRegionBuilder(EnhanceRegionBuilder $enhanceBuilder, InteractionRegistry $registry): void
    {
        $enhanceBuilder->link(function (\Noem\State\Chains\Params\BuildParams $context, callable $next) use ($registry) {
            $builder = $next($context);

            // Check if loader config exists with interactions
            if (!isset($context['loader']['array']['interactions'])) {
                return $builder;
            }

            $interactions = $context['loader']['array']['interactions'];
            if (!is_array($interactions)) {
                return $builder;
            }

            assert($builder instanceof \Noem\State\RegionBuilder);

            // Register each interaction via BuildStep
            foreach ($interactions as $interactionConfig) {
                if (!isset($interactionConfig['id'], $interactionConfig['type'], $interactionConfig['question'])) {
                    continue; // Invalid config, skip
                }

                $definition = new InteractionDefinition(
                    id: $interactionConfig['id'],
                    type: $interactionConfig['type'],
                    state: $interactionConfig['state'] ?? '',
                    question: $interactionConfig['question'],
                    options: $interactionConfig['options'] ?? null,
                    constraints: $interactionConfig['constraints'] ?? null,
                    metadata: $interactionConfig['metadata'] ?? []
                );

                $builder->addBuildStep(
                    new BuildStep\RegisterInteraction($interactionConfig['id'], $definition)
                );
            }

            return $builder;
        });
    }

    /**
     * Register discovery abilities for interaction introspection
     */
    private function registerDiscoveryAbilities(
        AbilityRegistry $abilityRegistry,
        InteractionRegistry $registry
    ): void {
        // enumerate-interactions ability
        $abilityRegistry->register(new AbilityDefinition(
            name: 'enumerate-interactions',
            description: 'List all registered interactions with their contracts',
            parameterSchema: [],
            responseSchema: [
                'type' => 'object',
                'properties' => [
                    'interactions' => [
                        'type' => 'array',
                        'items' => ['type' => 'object'],
                    ],
                ],
            ],
            /** @psalm-suppress UnusedClosureParam */
            handler: function (mixed $parameters = null) use ($registry): array {
                $interactions = array_map(
                    fn(InteractionDefinition $def) => $def->jsonSerialize(),
                    $registry->all()
                );

                return ['interactions' => $interactions];
            }
        ));

        // get-interaction ability
        $abilityRegistry->register(new AbilityDefinition(
            name: 'get-interaction',
            description: 'Retrieve specific interaction definition by id',
            parameterSchema: [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'string'],
                ],
                'required' => ['id'],
            ],
            responseSchema: [
                'type' => 'object',
                'nullable' => true,
            ],
            handler: function (mixed $parameters) use ($registry): ?array {
                $id = is_array($parameters) ? ($parameters['id'] ?? null) : null;

                if (!is_string($id)) {
                    throw new \InvalidArgumentException('get-interaction requires "id" parameter');
                }

                $definition = $registry->get($id);

                return $definition?->jsonSerialize();
            }
        ));

        // get-interactions-for-state ability
        $abilityRegistry->register(new AbilityDefinition(
            name: 'get-interactions-for-state',
            description: 'Retrieve all interactions associated with a specific state',
            parameterSchema: [
                'type' => 'object',
                'properties' => [
                    'state' => ['type' => 'string'],
                ],
                'required' => ['state'],
            ],
            responseSchema: [
                'type' => 'object',
                'properties' => [
                    'interactions' => [
                        'type' => 'array',
                        'items' => ['type' => 'object'],
                    ],
                ],
            ],
            handler: function (mixed $parameters = null) use ($registry): array {
                $state = is_array($parameters) ? ($parameters['state'] ?? null) : null;

                if (!is_string($state)) {
                    throw new \InvalidArgumentException('get-interactions-for-state requires "state" parameter');
                }

                $interactions = array_map(
                    fn(InteractionDefinition $def) => $def->jsonSerialize(),
                    $registry->getByState($state)
                );

                return ['interactions' => $interactions];
            }
        ));
    }

    /**
     * Extend YAML schema to support interactions blocks
     */
    private function extendYamlSchema(Schema $schema): void
    {
        $schema->link(
            function (
                \Noem\State\Feature\Loader\LoaderChains\Params\SchemaContext $context,
                callable $next
            ) {
            // Define interaction schema
                $interactionSchema = \Nette\Schema\Expect::listOf(
                    \Nette\Schema\Expect::structure([
                    'id' => \Nette\Schema\Expect::string()->required(),
                    'type' => \Nette\Schema\Expect::string()->required(),
                    'state' => \Nette\Schema\Expect::string(),
                    'question' => \Nette\Schema\Expect::string()->required(),
                    'options' => \Nette\Schema\Expect::anyOf(\Nette\Schema\Expect::array(), null),
                    'constraints' => \Nette\Schema\Expect::anyOf(\Nette\Schema\Expect::array(), null),
                    'metadata' => \Nette\Schema\Expect::anyOf(\Nette\Schema\Expect::array(), null),
                    ])
                );

            // Extend state schema
                $context->state = $context->state->extend([
                'interactions' => $interactionSchema,
                ]);

                // Extend region schema
                $context->region = $context->region->extend([
                    'interactions' => $interactionSchema,
                    'states' => \Nette\Schema\Expect::listOf($context->state),
                ]);

                return $next($context);
            }
        );
    }

    /**
     * Process interactions from YAML configuration
     */
    private function processYamlInteractions(
        EnhanceRegionBuilder $enhanceBuilder,
        InteractionRegistry $registry
    ): void {
        /** @psalm-suppress InvalidArgument */
        $enhanceBuilder->link(
            function (
                \Noem\State\Chains\Params\BuildParams $context,
                callable $next
            ) use ($registry) {
                if (isset($context['loader']['array'])) {
                    $config = $context['loader']['array'];

                    // Process region-level interactions
                    if (isset($config['interactions']) && is_array($config['interactions'])) {
                        foreach ($config['interactions'] as $interactionConfig) {
                            $this->registerInteractionFromConfig(
                                $registry,
                                $interactionConfig,
                                $config['name'] ?? 'unknown'
                            );
                        }
                    }

                    // Process state-level interactions
                    if (isset($config['states']) && is_array($config['states'])) {
                        foreach ($config['states'] as $stateConfig) {
                            if (isset($stateConfig['interactions']) && is_array($stateConfig['interactions'])) {
                                $stateName = $stateConfig['name'] ?? null;
                                if ($stateName !== null) {
                                    foreach ($stateConfig['interactions'] as $interactionConfig) {
                                        // For state-level interactions, use the state name
                                        $this->registerInteractionFromConfig($registry, $interactionConfig, $stateName);
                                    }
                                }
                            }
                        }
                    }
                }

                return $next($context);
            }
        );
    }

    /**
     * Register interaction from YAML configuration
     */
    private function registerInteractionFromConfig(
        InteractionRegistry $registry,
        array $config,
        string $defaultState
    ): void {
        $definition = new InteractionDefinition(
            id: $config['id'],
            type: $config['type'],
            state: $config['state'] ?? $defaultState,
            question: $config['question'],
            options: $config['options'] ?? null,
            constraints: $config['constraints'] ?? null,
            metadata: $config['metadata'] ?? null
        );

        $registry->register($definition);
    }
}
