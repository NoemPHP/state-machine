<?php

namespace Noem\State\Feature\Loader;

use Nette\Schema\Expect;
use Noem\State\BuildStep;
use Noem\State\Chains\ConnectedRegions;
use Noem\State\Chains\DispatchAction;
use Noem\State\Chains\EnhanceRegionBuilder;
use Noem\State\Chains\Params;
use Noem\State\Connection;
use Noem\State\Connection as C;
use Noem\State\Feature\Feature;
use Noem\State\Feature\Includes\Chains\LoadFile;
use Noem\State\Feature\Includes\IncludesFeature;
use Noem\State\Feature\Includes\LoadFileParams;
use Noem\State\Feature\Loader\LoaderChains\Params\SchemaContext;
use Noem\State\Feature\Loader\LoaderChains\Params\SpawnRegionParams;
use Noem\State\Feature\Loader\LoaderChains\Schema;
use Noem\State\Feature\Loader\LoaderChains\TransformArray;
use Noem\State\Feature\RequiresFeature;
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
#[RequiresFeature(IncludesFeature::class)]
class RegionLoader implements Feature
{
    /**
     * @throws ChainException
     */
    public function __invoke(ChainMail $chainMail): void
    {
        $chainMail
            ->supply(
                fn(): Container => new Container(),
                fn(): LoaderChains\Schema => new LoaderChains\Schema(),
                fn(): LoaderChains\TransformArray => new LoaderChains\TransformArray(),
                fn(ConnectedRegions $c): LoaderChains\SpawnRegion => new LoaderChains\SpawnRegion($c),
                fn(): RegionSpawnRegistry => new RegionSpawnRegistry(),
                fn(): YamlHelpers => new YamlHelpers(),
                fn(YamlHelpers $yamlHelpers): ConvertYaml => new ConvertYaml($yamlHelpers),
            );

        // Call methods directly instead of using $chainMail->use()
        // because we're already in the middle of boot() when this is invoked
        $chainMail->invoke($this->convertYaml(...));
        $chainMail->invoke($this->extendLoaderSchemaForSpawnerSupport(...));
        $chainMail->invoke($this->spawnRegionsOnActions(...));
        $chainMail->invoke($this->processSpawnerSchema(...));
    }

    /**
     * Transform YAML into compatible array shapes
     * Process array shapes into working Regions
     */
    public function convertYaml(
        EnhanceRegionBuilder $builderEnhancer,
        Schema               $schema,
        TransformArray       $transformArray,
        LoadFile $loadFile,
        ConvertYaml $convertYaml,
        YamlHelpers $yamlHelpers,
    ): void
    {
        // Register include helpers in the registry so they're available recursively
        $builderEnhancer->link(
            function (
                Params\BuildParams $args,
                callable           $next,
            ) use (
                $schema,
                $transformArray,
                $loadFile,
                $convertYaml,
                $yamlHelpers
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

                    // Register include helpers if not already registered
                    $this->registerIncludeHelpers(
                        $yamlHelpers,
                        $loadFile,
                        $args,
                        $convertYaml,
                        new LoadFileParams('', $args)
                    );

                    // User-provided helpers passed as additional (take precedence)
                    $array = $convertYaml->fromString($data, $loaderArgs['yamlHelpers'] ?? []);
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
     * Register include helpers in YamlHelpers registry if not already registered.
     *
     * This ensures include helpers are available recursively when parsing nested includes.
     */
    private function registerIncludeHelpers(
        YamlHelpers $yamlHelpers,
        LoadFile $loadFile,
        Params\BuildParams $buildParams,
        ConvertYaml $convertYaml,
        LoadFileParams $currentParams
    ): void
    {
        // Check if already registered to avoid duplicate registration errors
        $existingHelpers = $yamlHelpers->getHelpers();

        if (!isset($existingHelpers['include'])) {
            $yamlHelpers->register('include', new Helper\IncludeHelper(
                $loadFile,
                $buildParams,
                $convertYaml,
                $currentParams
            ));
        }

        if (!isset($existingHelpers['includeRelative'])) {
            $yamlHelpers->register('includeRelative', new Helper\IncludeRelativeHelper(
                $loadFile,
                $buildParams,
                $convertYaml,
                $currentParams,
                null // No current file for initial invocation
            ));
        }
    }

    /**
     * Extend the region schema to support spawning new regions when specific events occur
     */
    public function extendLoaderSchemaForSpawnerSupport(
        Schema $schema,
    ): void
    {
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
             * Update the reference on the state schema since we just produced a new object
             */
            $context->state = $context->state->extend([
                $spawnSchemaHandle => $spawnSchema,
            ]);
            
            /**
             * Update the region schema to use the extended state schema
             */
            $context->region = $context->region->extend([
                'states' => Expect::listOf($context->state),
            ]);

            return $next($context);
        });
    }

    /**
     * Enhance the RegionBuilder to handle spawning new regions based on schema definitions.
     */
    public function processSpawnerSchema(
        EnhanceRegionBuilder $builderEnhancer
    ): void
    {
        $builderEnhancer->link(
            function (
                Params\BuildParams $context,
                callable           $next
            ) {
                $builder = $next($context);
                assert($builder instanceof RegionBuilder);

                $loaderConfig = $context->config(\Noem\State\Chains\Params\Config\LoaderConfig::class);
                if (!$loaderConfig->hasStates()) {
                    return $builder;
                }
                foreach ($loaderConfig->states() as $state) {
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
                        $builder->addBuildStep(
                            new class(
                                $stateName,
                                fn() => $builder->newInstance()->build([
                                    'loader' => [
                                        'array' => $subRegionDefinition,
                                    ],
                                ]),
                                $guard,
                                $flags

                            ) implements BuildStep {
                                public function __construct(
                                    private $stateName,
                                    private $regionFactory,
                                    private $guard,
                                    private $connectionFlags,
                                )
                                {

                                }

                                public function callback(RegionBuilder $builder, callable $next, callable $first): Region
                                {
                                    $spawnRegistry = $builder->chainMail->invoke(fn(RegionSpawnRegistry $r) => $r);
                                    $region = $next($builder);

                                    $spawnRecord = new RegionSpawnRecord(
                                        $region,
                                        $this->stateName,
                                        $this->regionFactory,
                                        $this->guard,
                                        $this->connectionFlags
                                    );
                                    $spawnRegistry->addRecord($spawnRecord);

                                    return $region;
                                }
                            }

                        );
                    }
                }

                return $builder;
            }
        );
    }

    public static function regionSpawnStep(
        string   $stateName,
        callable $regionFactory,
        callable $guard,
        int      $connectionFlags = C::DYNAMIC | C::RECEIVE_EVENTS | C::RECEIVE_ACTIONS
    ): BuildStep
    {
        return new class(
            $stateName,
            $regionFactory,
            $guard,
            $connectionFlags
        ) implements BuildStep {
            public function __construct(
                private string   $stateName,
                private          $regionFactory,
                private          $guard,
                private int      $connectionFlags,
            )
            {
            }

            public function callback(RegionBuilder $builder, callable $next, callable $first): Region
            {
                $spawnRegistry = $builder->chainMail->invoke(fn(RegionSpawnRegistry $r) => $r);
                $region = $next($builder);

                $spawnRecord = new RegionSpawnRecord(
                    $region,
                    $this->stateName,
                    $this->regionFactory,
                    $this->guard,
                    $this->connectionFlags
                );
                $spawnRegistry->addRecord($spawnRecord);

                return $region;
            }
        };
    }

    public function spawnRegionsOnActions(
        DispatchAction           $dispatchAction,
        RegionSpawnRegistry      $spawnRegistry,
        LoaderChains\SpawnRegion $spawnRegion
    ): void
    {
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
