<?php

namespace Noem\State\Feature\Loader;

use Nette\Schema\Expect;
use Noem\State\Chains\ConnectedRegions;
use Noem\State\Chains\DispatchAction;
use Noem\State\Chains\EnhanceRegionBuilder;
use Noem\State\Chains\Params;
use Noem\State\Connection;
use Noem\State\Connection as C;
use Noem\State\Feature\Feature;
use Noem\State\Feature\Loader\LoaderChains\Params\SchemaContext;
use Noem\State\Feature\Loader\LoaderChains\Params\SpawnRegionParams;
use Noem\State\Feature\Loader\LoaderChains\Schema;
use Noem\State\Feature\Loader\LoaderChains\TransformArray;
use Noem\State\Middleware\Chain;
use Noem\State\Middleware\ChainException;
use Noem\State\Middleware\ChainMail;
use Noem\State\Region;
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
    /**
     * @throws ChainException
     */
    public function __invoke(ChainMail $chainMail): void
    {
        $chainMail
            ->supply(
                fn(): LoaderChains\Schema => new LoaderChains\Schema(),
                fn(): LoaderChains\TransformArray => new LoaderChains\TransformArray(),
                fn(ConnectedRegions $c): LoaderChains\SpawnRegion => new LoaderChains\SpawnRegion($c),
                fn(): RegionSpawnRegistry => new RegionSpawnRegistry(),
            )
            ->use($this->convertYaml(...))
            ->use($this->extendLoaderSchemaForSpawnerSupport(...))
            ->use($this->spawnRegionsOnActions(...))
            ->use($this->processSpawnerSchema(...));
    }

    /**
     * Transform YAML into compatible array shapes
     * Process array shapes into working Regions
     */
    public function convertYaml(
        EnhanceRegionBuilder $builderEnhancer,
        Schema $schema,
        TransformArray $transformArray,
    ): void {
        $builderEnhancer->link(
            function (
                Params\BuildParams $args,
                callable $next,
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
    }

    /**
     * Extend the region schema to support spawning new regions when specific events occur
     */
    public function extendLoaderSchemaForSpawnerSupport(
        Schema $schema,
    ): void {
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
    }

    /**
     * Enhance the RegionBuilder to handle spawning new regions based on schema definitions.
     */
    public function processSpawnerSchema(
        EnhanceRegionBuilder $builderEnhancer
    ): void {
        $builderEnhancer->link(
            function (
                Params\BuildParams $context,
                callable $next
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
                        [
                            'guard' => $guard,
                            'region' => $subRegionDefinition,
                        ] = $spawnerDefinition;
                        $defaultSharing = [
                            'meta' => true,
                        ];
                        $shared = $spawnerDefinition['shared'] ?? [];
                        $shared = array_merge($defaultSharing, $shared);
                        $flags = Connection::DYNAMIC
                            | Connection::RECEIVE_EVENTS
                            | Connection::RECEIVE_ACTIONS;
                        if ($shared['meta']) {
                            $flags = $flags | Connection::RECEIVE_META;
                        }
                        $builder->addStep(
                            self::regionSpawnStep(
                                $stateName,
                                fn() => $builder->newInstance()->build([
                                    'loader' => [
                                        'array' => $subRegionDefinition,
                                    ],
                                ]),
                                $guard,
                                $flags
                            )
                        );
                    }
                }

                return $builder;
            }
        );
    }

    public static function regionSpawnStep(
        string $stateName,
        callable $regionFactory,
        callable $guard,
        ?int $connectionFlags = C::DYNAMIC | C::RECEIVE_EVENTS | C::RECEIVE_ACTIONS
    ): \Closure {
        return function (
            RegionBuilder $builder,
            callable $next
        ) use (
            $stateName,
            $regionFactory,
            $guard,
            $connectionFlags
        ) {
            $spawnRegistry = $builder->chainMail->invoke(fn(RegionSpawnRegistry $r) => $r);
            $region = $next($builder);

            $spawnRecord = new RegionSpawnRecord(
                $region,
                $stateName,
                $regionFactory,
                $guard,
                $connectionFlags
            );
            $spawnRegistry->addRecord($spawnRecord);

            return $region;
        };
    }

    public function spawnRegionsOnActions(
        DispatchAction $dispatchAction,
        RegionSpawnRegistry $spawnRegistry,
        LoaderChains\SpawnRegion $spawnRegion
    ): void {
        $dispatchAction->link(
            function (Params\Action $action, callable $next) use ($spawnRegistry, $spawnRegion): string {
                foreach ($spawnRegistry->records as $record) {
                    $spawnParams = new SpawnRegionParams($record, $action);
                    $spawnRegion->call($spawnParams);
                }

                return $next($action);
            }
        );
    }
}
