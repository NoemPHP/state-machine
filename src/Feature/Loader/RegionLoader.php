<?php

namespace Noem\State\Feature\Loader;

use Nette\Schema\Expect;
use Noem\State\Chains\ConnectedRegions;
use Noem\State\Chains\DispatchAction;
use Noem\State\Chains\EnhanceRegionBuilder;
use Noem\State\Chains\InvokeCallback;
use Noem\State\Chains\Params;
use Noem\State\Connection;
use Noem\State\Feature\Feature;
use Noem\State\Feature\Loader\LoaderChains\Context\SchemaContext;
use Noem\State\Feature\Loader\LoaderChains\Schema;
use Noem\State\Feature\Loader\LoaderChains\TransformArray;
use Noem\State\Middleware\Chain;
use Noem\State\Middleware\ChainMail;
use Noem\State\RegionBuilder;
use Noem\State\Util\ParameterDeriver;

/**
 * The Noem State Machine's RegionLoader class is responsible for loading and parsing
 * state machine configurations from YAML or PHP arrays.
 * It contains methods to resolve helper functions, extract configuration data
 * from state definitions, and create callbacks for transition guards and
 * event handlers like entering/exiting states or handling actions.
 * It can load configurations from YAML input using the fromYaml() method
 * or from PHP arrays using the fromArray() method.
 */
class RegionLoader implements Feature
{
    public function __invoke(ChainMail $chainMail): void
    {
        $chainMail
            ->supply(
                fn(): Schema => new Schema(),
                fn(): TransformArray => new TransformArray(),
            )
            /**
             * Inject the Loader chain into the regular build pipeline
             */
            ->use(
                function (
                    EnhanceRegionBuilder $builderEnhancer,
                    Schema $schema,
                    TransformArray $transformArray,
                    DispatchAction $dispatchAction,
                    ConnectedRegions $connectedRegions
                ): void {
                    /**
                     * Transform YAML into compatible array shapes
                     * Process array shapes into working Regions
                     */
                    $builderEnhancer->link(
                        function (
                            Params\BuildParams $args,
                            callable $next,
                            callable $first
                        ) use (
                            $schema,
                            $transformArray
                        ): RegionBuilder {
                            if (!isset($args['loader'])) {
                                return $next($args);
                            }
                            $loaderArgs = $args['loader'];

                            if (isset($loaderArgs['yaml'])) {
                                $data = $loaderArgs['yaml'];
                                /**
                                 * If the context is a valid file path, load the contents
                                 */
                                $data = is_readable($data)
                                    ? file_get_contents($data)
                                    : $data;
                                $array = new ConvertYaml()->fromString($data, $loaderArgs['yamlHelpers'] ?? []);
                                $loaderArgs['array'] = $array;
                                $args['loader'] = $loaderArgs;
                            }
                            if (isset($loaderArgs['array'])) {
                                new ProcessArray($schema, $transformArray)->fromData(
                                    $loaderArgs['array'],
                                    $args->builder
                                );
                            }

                            return $next($args);
                        }
                    );

                    /**
                     * Extend the region schema to support spawning new regions when specific events occur
                     */
                    $schema->link(function (SchemaContext $context, callable $next) {
                        $spawnSchemaHandle = 'spawn';
                        $subRegionSpawnerSchema = Expect::structure([
                            'guard' => $context->callback,
                            'region' => Expect::array(),
                            'shared' => Expect::array(),
                        ]);
                        $spawnSchema = Expect::listOf($subRegionSpawnerSchema);
                        $context->addCustomSchema($spawnSchemaHandle, $spawnSchema);
                        /**
                         * Update the reference on the region schema since we just produced a new object
                         */
                        $context->state = $context->state->extend([
                            $spawnSchemaHandle => $spawnSchema,
                        ]);

                        return $next($context);
                    });

                    $spawnerChain = new  Chain();
                    /**
                     * Enhance the RegionBuilder to handle spawning new regions based on schema definitions.
                     */
                    $builderEnhancer->link(
                        function (
                            Params\BuildParams $context,
                            callable $next
                        ) use (
                            $connectedRegions,
                            $spawnerChain
                        ) {
                            $builder = $next($context);
                            assert($builder instanceof RegionBuilder);

                            if (!isset($context['loader']['array']['states'])) {
                                return $builder;
                            }
                            foreach ($context['loader']['array']['states'] as $state) {
                                if (!isset($state['spawn'])) {
                                    continue;
                                }
                                foreach ($state['spawn'] as $spawnerDefinition) {
                                    $stateName = $state['name'];

                                    $builder->addStep(
                                        function (
                                            RegionBuilder $builder,
                                            callable $next
                                        ) use (
                                            $stateName,
                                            $spawnerDefinition,
                                            $spawnerChain,
                                            $connectedRegions
                                        ) {
                                            $region = $next($builder);
                                            $spawnerChain->link(
                                                function (
                                                    Params\Action $action,
                                                    callable $next
                                                ) use (
                                                    $builder,
                                                    $region,
                                                    $stateName,
                                                    $spawnerDefinition,
                                                    $connectedRegions
                                                ) {
                                                    if ($region !== $action->region) {
                                                        return $next($action);
                                                    }
                                                    if ($action->currentState !== $stateName) {
                                                        return $next($action);
                                                    }
                                                    [
                                                        'guard' => $guard,
                                                        'region' => $subRegionDefinition,
                                                    ] = $spawnerDefinition;

                                                    $defaultSharing = [
                                                        'meta' => true,
                                                    ];
                                                    $shared = $spawnerDefinition['shared'] ?? [];
                                                    $shared = array_merge($defaultSharing, $shared);
                                                    /**
                                                     * Inspect the defined predicate function.
                                                     * If it matches our trigger payload, then we can invoke it.
                                                     */
                                                    if (
                                                        !ParameterDeriver::isCompatibleParameter(
                                                            $guard,
                                                            $action->payload
                                                        )
                                                    ) {
                                                        return $next($action);
                                                    }
                                                    if (!$guard($action->payload)) {
                                                        /**
                                                         * Predicate returned false, so we bail
                                                         */
                                                        return $next($action);
                                                    }
                                                    $subRegion = $builder->newInstance()->build([
                                                        'loader' => [
                                                            'array' => $subRegionDefinition,
                                                        ],
                                                    ]);
                                                    $flags = Connection::DYNAMIC
                                                        | Connection::RECEIVE_EVENTS
                                                        | Connection::RECEIVE_ACTIONS;

                                                    if ($shared['meta']) {
                                                        $flags = $flags | Connection::RECEIVE_META;
                                                    }
                                                    /**
                                                     * Create a Connection with a predicate that ties
                                                     * the newly spawned region to its parent region/state
                                                     */
                                                    $connection = new Connection(
                                                        $region,
                                                        $subRegion,
                                                        $flags,
                                                        function (Connection $c) use (
                                                            $stateName,
                                                            $guard,
                                                            $action
                                                        ): bool {
                                                            return $c->local->currentState() === $stateName;
                                                        }
                                                    );
                                                    $connectedRegions->addConnection($connection);

                                                    return $next($action);
                                                }
                                            );

                                            return $region;
                                        }
                                    );
                                }
                            }

                            return $builder;
                        }
                    );

                    $dispatchAction->link(
                        function (Params\Action $action, callable $next) use ($spawnerChain): string {
                            $spawnerChain->call($action);

                            return $next($action);
                        }
                    );
                },
            );
    }
}
