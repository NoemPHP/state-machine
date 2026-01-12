<?php

declare(strict_types=1);

namespace Noem\State\Feature\Presentation;

use Noem\State\Chains\Params\Config\LoaderConfig;
use Noem\State\Feature\Abilities\AbilityDefinition;
use Noem\State\Feature\Abilities\AbilityRegistry;
use Noem\State\Feature\ExtendedState\ContextChains\BoundAccess;
use Noem\State\Feature\ExtendedState\ContextChains\Params\BoundAccessParams;
use Noem\State\Feature\Feature;
use Noem\State\Feature\JsonSchema\JsonSchemaFeature;
use Noem\State\Middleware\ChainMail;
use Noem\State\Region;
use RuntimeException;

/**
 * Schema-enforced declarative state exposure for AI agents and monitoring tools.
 *
 * Provides RegionPresentation value objects, PresentationRegistry storage,
 * and discovery abilities for external introspection.
 *
 * Hard dependencies:
 * - JsonSchemaFeature (schema validation)
 * - ExtendedState (context retrieval)
 * - AbilitiesFeature (discovery abilities)
 */
class PresentationFeature implements Feature
{
    #[\Override]
    public function __invoke(ChainMail $chainMail): void
    {
        // Register PresentationRegistry in ChainMail
        $registry = new PresentationRegistry();
        $chainMail->supply(fn(): PresentationRegistry => $registry);

        // Wire presentation() into RegionBuilder via BuilderMethodCall chain
        $chainMail->use(
            function (?\Noem\State\Chains\BuilderMethodCall $builderMethodCall = null) use ($registry) {
                if ($builderMethodCall !== null) {
                    $this->wireRegionBuilderMethod($builderMethodCall, $registry);
                }
            }
        );

        // Validate dependencies and wire components
        // Use chainMail->use() to defer execution until all features are loaded
        $chainMail->use(
            function (
                ?BoundAccess $boundAccess = null,
                ?AbilityRegistry $abilityRegistry = null
            ) use ($registry) {
                // Validate dependencies
                if ($boundAccess === null) {
                    throw new RuntimeException(
                        'PresentationFeature requires ExtendedState to be loaded first'
                    );
                }

                if ($abilityRegistry === null) {
                    throw new RuntimeException(
                        'PresentationFeature requires AbilitiesFeature to be loaded first'
                    );
                }

                // Register discovery abilities
                $this->registerDiscoveryAbilities($abilityRegistry, $registry);

                // Bind $this->presentation() method to BoundAccess
                $this->bindPresentationMethod($boundAccess, $registry);
            }
        );
    }

    /**
     * Wire presentation() method into RegionBuilder via BuilderMethodCall chain
     */
    private function wireRegionBuilderMethod(
        \Noem\State\Chains\BuilderMethodCall $builderMethodCall,
        PresentationRegistry $registry
    ): void {
        $builderMethodCall->link(
            function (
                \Noem\State\Chains\Params\BuilderMethodCallParams $params,
                callable $next
            ) use ($registry) {
                // Only intercept calls to 'presentation' method
                if ($params->method !== 'presentation') {
                    return $next($params);
                }

                // Extract arguments - handle both positional and named arguments
                $args = $params->arguments;

                // Support both positional and named arguments
                $key = $args['key'] ?? $args[0] ?? null;
                $label = $args['label'] ?? $args[1] ?? null;
                $intent = $args['intent'] ?? $args[2] ?? null;
                $metadata = $args['metadata'] ?? $args[3] ?? null;
                $predicate = $args['predicate'] ?? $args[4] ?? null;

                if (!is_string($key) || !is_string($label) || !is_string($intent)) {
                    throw new \InvalidArgumentException(
                        'presentation() requires string $key, $label, and $intent parameters'
                    );
                }

                $presentation = new RegionPresentation(
                    key: $key,
                    label: $label,
                    intent: $intent,
                    metadata: $metadata,
                    predicate: $predicate
                );

                $registry->register($presentation);

                // Return the builder for chaining
                return $params->builder;
            },
            prepend: true
        );
    }

    /**
     * Register enumerate-presentations and get-presented-state abilities
     */
    private function registerDiscoveryAbilities(
        AbilityRegistry $abilityRegistry,
        PresentationRegistry $presentationRegistry
    ): void {
            // enumerate-presentations ability
            $abilityRegistry->register(new AbilityDefinition(
                name: 'enumerate-presentations',
                description: 'Lists all registered presentations with schemas and metadata',
                parameterSchema: [],
                responseSchema: [
                    'type' => 'object',
                    'properties' => [
                        'presentations' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'key' => ['type' => 'string'],
                                    'label' => ['type' => 'string'],
                                    'intent' => ['type' => 'string'],
                                    'metadata' => ['type' => ['object', 'null']],
                                    'schema' => ['type' => 'object'],
                                ],
                            ],
                        ],
                    ],
                ],
                /**
                 * @psalm-suppress UnusedClosureParam
                 * @psalm-suppress UndefinedThisPropertyFetch
                 */
                handler: function (mixed $parameters = null) use ($presentationRegistry): array {
                    $presentations = [];

                    foreach ($presentationRegistry->all() as $presentation) {
                        // Evaluate predicate if present - $this is bound to Region at runtime
                        if ($presentation->predicate !== null) {
                            $region = $this->region;
                            try {
                                $visible = ($presentation->predicate)($region);
                                if (!$visible) {
                                    continue; // Skip presentations with false predicates
                                }
                            } catch (\Throwable) {
                                continue; // Treat predicate exceptions as false
                            }
                        }

                        // Serialize presentation and include schema
                        $serialized = $presentation->jsonSerialize();
                        $schema = $presentationRegistry->getSchema($presentation->key);
                        $serialized['schema'] = $schema ?? [];

                        $presentations[] = $serialized;
                    }

                    return ['presentations' => $presentations];
                }
            ));

            // get-presented-state ability
            $abilityRegistry->register(new AbilityDefinition(
                name: 'get-presented-state',
                description: 'Retrieves actual context values for presentations',
                parameterSchema: [
                    'type' => 'object',
                    'properties' => [
                        'keys' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                        ],
                    ],
                ],
                responseSchema: [
                    'type' => 'object',
                    'properties' => [
                        'values' => ['type' => 'object'],
                    ],
                ],
                /**
                 * @psalm-suppress UndefinedThisPropertyFetch
                 * @psalm-suppress UndefinedMethod
                 */
                handler: function (mixed $parameters = null) use ($presentationRegistry): array {
                    $requestedKeys = is_array($parameters) ? ($parameters['keys'] ?? null) : null;
                    $values = [];

                    $presentations = $presentationRegistry->all();

                    // Filter to requested keys if specified
                    if ($requestedKeys !== null) {
                        $presentations = array_filter(
                            $presentations,
                            fn($key) => in_array($key, $requestedKeys),
                            ARRAY_FILTER_USE_KEY
                        );
                    }

                    foreach ($presentations as $presentation) {
                        // Evaluate predicate if present - $this is bound to Region at runtime
                        if ($presentation->predicate !== null) {
                            $region = $this->region;
                            try {
                                $visible = ($presentation->predicate)($region);
                                if (!$visible) {
                                    continue; // Skip presentations with false predicates
                                }
                            } catch (\Throwable) {
                                continue; // Treat predicate exceptions as false
                            }
                        }

                        // Retrieve value from ExtendedState - $this->get() available via BoundAccess
                        $value = $this->get($presentation->key);

                        $values[$presentation->key] = [
                            'value' => $value,
                            'label' => $presentation->label,
                            'intent' => $presentation->intent,
                            'metadata' => $presentation->metadata,
                        ];
                    }

                    return ['values' => $values];
                }
            ));
    }

    /**
     * Bind $this->presentation() method to BoundAccess context
     *
     * Returns callable unregister function
     */
    private function bindPresentationMethod(
        BoundAccess $boundAccess,
        PresentationRegistry $registry
    ): void {
            $boundAccess->link(function (BoundAccessParams $params, callable $next) use ($registry) {
                if ($params->type !== BoundAccessParams::TYPE_METHOD || $params->name !== 'presentation') {
                    return $next($params);
                }

                [$key, $label, $intent, $metadata, $predicate] = array_pad($params->payload, 5, null);

                if (!is_string($key) || !is_string($label) || !is_string($intent)) {
                    throw new \InvalidArgumentException(
                        'presentation() requires string $key, $label, and $intent parameters'
                    );
                }

                $presentation = new RegionPresentation(
                    key: $key,
                    label: $label,
                    intent: $intent,
                    metadata: $metadata,
                    predicate: $predicate
                );

                $registry->register($presentation);

                // Return unique unregister callable
                $currentPresentation = $presentation;

                return function () use ($registry, $key, $currentPresentation) {
                    // Only unregister if this specific registration is still active
                    if ($registry->get($key) === $currentPresentation) {
                        $registry->unregister($key);
                    }
                };
            }, prepend: true);
    }
}
