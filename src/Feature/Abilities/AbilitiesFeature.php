<?php

declare(strict_types=1);

namespace Noem\State\Feature\Abilities;

use Noem\State\Chains\EnhanceRegionBuilder;
use Noem\State\Chains\Notification;
use Noem\State\Feature\Abilities\Chains\ExecuteAbilityHandler;
use Noem\State\Feature\Abilities\Chains\InvokeAbility;
use Noem\State\Feature\Abilities\Chains\ProcessAbilityResult;
use Noem\State\Feature\Abilities\Chains\Params\InvokeAbility as InvokeAbilityParams;
use Noem\State\Feature\ExtendedState\ContextChains\BoundAccess;
use Noem\State\Feature\ExtendedState\ContextChains\Params\BoundAccessParams;
use Noem\State\Feature\Feature;
use Noem\State\Feature\Loader\LoaderChains\Schema;
use Noem\State\Feature\Message\MessageFeature;
use Noem\State\Feature\RequiresFeature;
use Noem\State\Middleware\ChainMail;

/**
 * Schema-validated, discoverable cross-region operations with BoundAccess integration
 *
 * Provides abilities system built on MessageFeature correlation infrastructure.
 * Enables regions to expose and invoke operations through $this->abilities() API
 * with schema validation, middleware interception, and meta-reflexive enumeration.
 *
 * Dependencies:
 * - MessageFeature (required): Provides correlation-based request-response
 * - ExtendedState (optional): Enables $this->abilities() API via BoundAccess
 * - RegionLoader (optional): Enables YAML schema extension for declarative abilities
 */
#[RequiresFeature(MessageFeature::class)]
class AbilitiesFeature implements Feature
{
    public function __invoke(ChainMail $chainMail): void
    {
        // MessageFeature dependency guaranteed by RequiresFeature attribute

        // Register AbilityRegistry in ChainMail
        $registry = new AbilityRegistry();
        $chainMail->supply(
            fn(): AbilityRegistry => $registry,
            fn(): ExecuteAbilityHandler => new ExecuteAbilityHandler(),
            fn(Notification $n): ProcessAbilityResult => new ProcessAbilityResult($n),
            fn(Notification $n, ExecuteAbilityHandler $e, ProcessAbilityResult $r): InvokeAbility => new InvokeAbility($registry, $n, $e, $r)
        );

        // Wire abilities() method into BoundAccess if ExtendedState is loaded
        // Use chainMail->use() to defer execution until all features are loaded
        $chainMail->use(
            function (
                ?BoundAccess $boundAccess = null,
                ?InvokeAbility $invokeAbilityChain = null
            ) use ($registry) {
                if ($boundAccess !== null && $invokeAbilityChain !== null) {
                    $this->wireBoundAccess($boundAccess, $invokeAbilityChain, $registry);
                }
                // If BoundAccess is null, ExtendedState wasn't loaded - that's fine
            }
        );

        // Extend YAML schema to support abilities at region and state level (optional dependency)
        $chainMail->use(function (?Schema $schema = null) {
            $schema && $this->extendYamlSchema($schema);
        });

        // Process abilities from YAML configuration (optional dependency)
        $chainMail->use(function (?EnhanceRegionBuilder $builder = null) use ($registry) {
            $builder && $this->processAbilitiesConfig($builder, $registry);
        });

        // Register built-in enumerate-abilities ability
        $this->registerEnumerateAbility($registry);
    }

    /**
     * Wire abilities() method directly into BoundAccess chain
     *
     * Intercepts $this->abilities() calls in state callbacks.
     * Two modes:
     * - abilities(): Returns AbilityRuntimeHelper for ->register()
     * - abilities($name, $params): Invokes ability and returns AbilityMessage
     */
    private function wireBoundAccess(
        BoundAccess $boundAccess,
        InvokeAbility $invokeAbilityChain,
        AbilityRegistry $registry
    ): void {
        $boundAccess->link(function (BoundAccessParams $params, callable $next) use ($invokeAbilityChain, $registry) {
            // Only intercept method calls named 'abilities'
            if ($params->type !== BoundAccessParams::TYPE_METHOD) {
                return $next($params);
            }

            if ($params->name !== 'abilities') {
                return $next($params);
            }

            // Extract arguments: abilities($name, $parameters = null)
            $args = $params->payload ?? [];
            $abilityName = $args[0] ?? null;
            $parameters = $args[1] ?? null;

            // If no ability name provided, return runtime helper for ->register()
            if ($abilityName === null) {
                return new AbilityRuntimeHelper($params->region, $registry);
            }

            // Create InvokeAbility params and call chain
            $invokeParams = new InvokeAbilityParams(
                region: $params->region,
                abilityName: $abilityName,
                parameters: $parameters,
            );

            $message = $invokeAbilityChain->call($invokeParams);

            // Return an AbilityInvocation wrapper that supports both:
            // - yield from $this->abilities(...) - generator pattern
            // - $this->abilities(...)->then(...) - message pattern
            $generator = (function () use ($message) {
                $receivedResponse = null;

                // Register then() handler - will be called immediately if response cached,
                // or later when response arrives
                $message->then(function ($response) use (&$receivedResponse) {
                    $receivedResponse = $response;
                });

                // If response already arrived (sync handler), $receivedResponse is set
                // If not yet arrived (async handler), yield until it arrives
                while ($receivedResponse === null) {
                    yield;
                }

                return $message;
            })();

            return new AbilityInvocation($generator, $message);
        });
    }

    /**
     * Register built-in enumerate-abilities ability
     *
     * Meta-reflexive ability that lists all available abilities.
     */
    private function registerEnumerateAbility(AbilityRegistry $registry): void
    {
        $definition = new AbilityDefinition(
            name: 'enumerate-abilities',
            description: 'List all available abilities with their schemas and descriptions',
            parameterSchema: [
                'type' => 'object',
                'properties' => [
                    'filter' => [
                        'type' => 'string',
                        'description' => 'Optional regex pattern to filter ability names',
                    ],
                ],
            ],
            responseSchema: [
                'type' => 'object',
                'properties' => [
                    'abilities' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'name' => ['type' => 'string'],
                                'description' => ['type' => 'string'],
                                'parameterSchema' => ['type' => 'object'],
                                'responseSchema' => ['type' => 'object'],
                            ],
                        ],
                    ],
                ],
            ],
            handler: function (mixed $parameters = null) use ($registry): array {
                $filter = is_array($parameters) ? ($parameters['filter'] ?? null) : null;

                $abilities = array_map(
                    function (AbilityDefinition $def): array {
                        return [
                            'name' => $def->name,
                            'description' => $def->description,
                            'parameterSchema' => $def->parameterSchema,
                            'responseSchema' => $def->responseSchema,
                            // Exclude handler callable - not serializable
                            // Exclude predicate - internal implementation detail
                        ];
                    },
                    array_values($registry->all())
                );

                // Filter by regex if provided
                if ($filter !== null && is_string($filter)) {
                    $abilities = array_filter(
                        $abilities,
                        fn(array $ability) => preg_match('/' . $filter . '/', $ability['name']) === 1
                    );
                    $abilities = array_values($abilities); // Re-index
                }

                return [
                    'abilities' => $abilities,
                ];
            },
        );

        $registry->register($definition);
    }

    /**
     * Extend YAML schema to support abilities at region and state level
     *
     * Adds 'abilities' key to both region and state schemas with validation for:
     * - name (required string)
     * - handler (required callable)
     * - description (optional string)
     * - parameterSchema (optional array)
     * - responseSchema (optional array)
     * - predicate (optional callable)
     */
    private function extendYamlSchema(
        ?Schema $schema = null
    ): void {
        if ($schema === null) {
            return; // RegionLoader not loaded, no YAML support
        }

        $schema->link(function (\Noem\State\Feature\Loader\LoaderChains\Params\SchemaContext $context, callable $next) {
            // Define ability schema
            $abilitySchema = \Nette\Schema\Expect::listOf(
                \Nette\Schema\Expect::structure([
                    'name' => \Nette\Schema\Expect::string()->required(),
                    'handler' => \Nette\Schema\Expect::mixed()->required(), // Loader will process callbacks
                    'description' => \Nette\Schema\Expect::string(''),
                    'parameterSchema' => \Nette\Schema\Expect::array([]),
                    'responseSchema' => \Nette\Schema\Expect::array([]),
                    'predicate' => \Nette\Schema\Expect::mixed()->nullable(),
                ])
            );

            // Extend state schema
            $context->state = $context->state->extend([
                'abilities' => $abilitySchema,
            ]);

            // Extend region schema with both the abilities key and updated states reference
            $context->region = $context->region->extend([
                'abilities' => $abilitySchema,
                'states' => \Nette\Schema\Expect::listOf($context->state),
            ]);

            return $next($context);
        });
    }

    /**
     * Process abilities from YAML configuration
     *
     * Hooks into EnhanceRegionBuilder to extract abilities from config and register them.
     * Handles both region-level (global) and state-level (scoped) abilities.
     * State-level abilities are wrapped with predicates to check active state.
     */
    private function processAbilitiesConfig(
        EnhanceRegionBuilder $enhanceBuilder,
        AbilityRegistry $registry
    ): void {
        $enhanceBuilder->link(function (\Noem\State\Chains\Params\BuildParams $context, callable $next) use ($registry) {
            // Check if loader config exists and register abilities BEFORE calling next
            // This ensures abilities are available during build process
            if (isset($context['loader']['array'])) {
                $config = $context['loader']['array'];

                // Register region-level abilities (global scope)
                if (isset($config['abilities']) && is_array($config['abilities'])) {
                    foreach ($config['abilities'] as $abilityConfig) {
                        $this->registerAbilityFromConfig($registry, $abilityConfig, null);
                    }
                }

                // Register state-level abilities (state-scoped)
                if (isset($config['states']) && is_array($config['states'])) {
                    foreach ($config['states'] as $stateConfig) {
                        if (isset($stateConfig['abilities']) && is_array($stateConfig['abilities'])) {
                            $stateName = $stateConfig['name'] ?? null;
                            if ($stateName === null) {
                                continue; // Invalid state config, skip
                            }

                            foreach ($stateConfig['abilities'] as $abilityConfig) {
                                $this->registerAbilityFromConfig($registry, $abilityConfig, $stateName);
                            }
                        }
                    }
                }
            }

            // Now process the builder
            return $next($context);
        });
    }

    /**
     * Register ability from YAML configuration
     *
     * Creates AbilityDefinition from config array and registers it in the registry.
     * If stateName is provided, wraps predicate to check active state.
     *
     * @param AbilityRegistry $registry Registry to register ability in
     * @param array $config Ability configuration from YAML
     * @param string|null $stateName If provided, creates state-scoped ability
     */
    private function registerAbilityFromConfig(
        AbilityRegistry $registry,
        array $config,
        ?string $stateName = null
    ): void {
        // Extract configuration
        $name = $config['name'];
        $handler = $config['handler'];
        $description = $config['description'] ?? '';
        $parameterSchema = $config['parameterSchema'] ?? [];
        $responseSchema = $config['responseSchema'] ?? [];
        $predicate = $config['predicate'] ?? null;

        // For state-level abilities, wrap predicate to check active state
        if ($stateName !== null) {
            $originalPredicate = $predicate;
            $predicate = function (\Noem\State\Region $region) use ($stateName, $originalPredicate): bool {
                // Check if state is active
                if ($region->currentState() !== $stateName) {
                    return false;
                }

                // If original predicate exists, evaluate it
                if ($originalPredicate !== null && is_callable($originalPredicate)) {
                    return (bool)$originalPredicate($region);
                }

                return true;
            };
        }

        // Create and register ability definition
        $definition = new AbilityDefinition(
            name: $name,
            description: $description,
            parameterSchema: $parameterSchema,
            responseSchema: $responseSchema,
            handler: $handler,
            predicate: $predicate
        );

        $registry->register($definition);
    }
}
