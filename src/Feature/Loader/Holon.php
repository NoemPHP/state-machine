<?php

declare(strict_types=1);

namespace Noem\State\Feature\Loader;

use Noem\State\Feature\Feature;
use Noem\State\Middleware\ChainMail;
use Noem\State\Region;
use Noem\State\RegionBuilder;
use Psr\Container\ContainerInterface;
use RuntimeException;

/**
 * Holon enables bootstrapping complete state machines from a single YAML file.
 *
 * A "holon" is something that is simultaneously a whole and a part of a larger whole.
 * This reflects how these machines can be self-contained yet also composable.
 *
 * This solves the chicken/egg problem by using a two-phase bootstrap:
 * 1. Phase 1: Parse machine configuration and setup container
 * 2. Phase 2: Build the actual state machine with all features
 */
class Holon
{
    private const DEFAULT_MAX_ITERATIONS = 10000;

    /**
     * Bootstrap a complete state machine from YAML
     *
     * @param string $yaml The YAML content or file path
     * @param array $options Additional options for bootstrapping
     * @return Region|mixed Returns Region or event loop result if autoRun is true
     */
    public static function fromYaml(string $yaml, array $options = []): mixed
    {
        // If the yaml looks like a file path, try to load it
        if (!str_contains($yaml, "\n") && is_readable($yaml)) {
            $yaml = file_get_contents($yaml);
        }

        // Phase 1: Parse the YAML to extract machine configuration
        // We need to parse carefully to avoid evaluating state PHP code without container
        $converter = new ConvertYaml();

        // First, parse without any PHP evaluation to get structure
        $rawYaml = \Symfony\Component\Yaml\Yaml::parse($yaml, \Symfony\Component\Yaml\Yaml::PARSE_CUSTOM_TAGS);

        // Extract machine configuration and parse it with bootstrap helpers
        $machineConfig = $rawYaml['machine'] ?? [];
        if ($machineConfig) {
            $machineYaml = \Symfony\Component\Yaml\Yaml::dump(['machine' => $machineConfig], 10);
            $parsedMachine = $converter->fromString($machineYaml, self::getBootstrapHelpers());
            $machineConfig = $parsedMachine['machine'] ?? [];
        }

        // Keep states and regions as raw data (not yet parsed with PHP helpers)
        $statesConfig = $rawYaml['states'] ?? [];
        $regionsConfig = $rawYaml['regions'] ?? [];

        // Build the container
        $container = self::buildContainer($machineConfig['container'] ?? []);

        // Setup features
        $features = self::instantiateFeatures($machineConfig['features'] ?? [], $container);

        // Add RegionLoader if not already present
        if (!self::hasRegionLoader($features)) {
            $features[] = new RegionLoader();
        }

        // Phase 2: Build the region with full feature support
        $builder = new RegionBuilder();

        // Enable all features
        foreach ($features as $feature) {
            $builder->enableFeatures($feature);
        }

        // Setup YAML helpers with container access
        $yamlHelpers = self::getYamlHelpers($container);

        // Construct region configuration (without machine section)
        // by keeping only the state machine structure
        $regionConfig = [];
        if ($statesConfig) {
            // Transform states recursively: extract initial/final markers and convert to region-level
            $regionConfig = self::transformStatesConfig(['states' => $statesConfig]);
        }
        if ($regionsConfig) {
            $regionConfig['regions'] = $regionsConfig;
        }
        // Add initial/final from top-level config if present (and not already set from states)
        if (!isset($regionConfig['initial']) && isset($rawYaml['initial'])) {
            $regionConfig['initial'] = $rawYaml['initial'];
        }
        if (!isset($regionConfig['final']) && isset($rawYaml['final'])) {
            $regionConfig['final'] = $rawYaml['final'];
        }
        // Include interactions from top-level config for InteractionRegistryFeature
        if (isset($rawYaml['interactions'])) {
            $regionConfig['interactions'] = $rawYaml['interactions'];
        }

        // Convert region config to YAML so it can be parsed with container-aware helpers
        $regionYaml = \Symfony\Component\Yaml\Yaml::dump($regionConfig, 10);

        // Build the region with the cleaned configuration as YAML
        $builderArgs = [
            'loader' => [
                'yaml' => $regionYaml,
                'yamlHelpers' => $yamlHelpers,
            ],
        ];

        // Merge any additional options
        if (isset($options['builderArgs'])) {
            $builderArgs = array_merge_recursive($builderArgs, $options['builderArgs']);
        }

        $region = $builder->build($builderArgs);

        // Handle event loop configuration
        $eventLoopConfig = $machineConfig['eventLoop'] ?? [];

        // Allow options parameter to override YAML autoRun setting
        // This is critical for summon() to prevent deadlocks
        $autoRun = $options['autoRun'] ?? ($eventLoopConfig['autoRun'] ?? false);

        if ($autoRun) {
            return self::runEventLoop($region, $eventLoopConfig, $container);
        }

        return $region;
    }

    /**
     * Build a container from configuration
     */
    private static function buildContainer(array $containerConfig): ContainerInterface
    {
        $services = [];
        $factories = [];

        foreach ($containerConfig['services'] ?? [] as $id => $definition) {
            if (isset($definition['factory'])) {
                // Factory-based service - store it even if not callable (will be validated on get())
                $factories[$id] = $definition['factory'];
            } elseif (isset($definition['value'])) {
                // Direct value
                $services[$id] = $definition['value'];
            } elseif (isset($definition['class'])) {
                // Class instantiation
                $class = $definition['class'];
                $args = $definition['arguments'] ?? [];
                $factories[$id] = fn() => new $class(...$args);
            }
        }

        // Create a simple container implementation
        return new class ($services, $factories) implements ContainerInterface {
            private array $services;
            private array $factories;
            private array $resolved = [];

            public function __construct(array $services, array $factories)
            {
                $this->services = $services;
                $this->factories = $factories;
            }

            public function get(string $id): mixed
            {
                if (isset($this->services[$id])) {
                    return $this->services[$id];
                }

                if (isset($this->resolved[$id])) {
                    return $this->resolved[$id];
                }

                if (isset($this->factories[$id])) {
                    $factory = $this->factories[$id];
                    if (!is_callable($factory)) {
                        throw new RuntimeException("Factory for service '$id' is not callable");
                    }
                    $this->resolved[$id] = $factory($this);
                    return $this->resolved[$id];
                }

                throw new RuntimeException("Service '$id' not found in container");
            }

            public function has(string $id): bool
            {
                return isset($this->services[$id]) || isset($this->factories[$id]);
            }
        };
    }

    /**
     * Instantiate features from configuration
     */
    private static function instantiateFeatures(array $featuresConfig, ContainerInterface $container): array
    {
        $features = [];

        foreach ($featuresConfig as $featureConfig) {
            if (is_string($featureConfig)) {
                // Simple class name
                $featureConfig = ['class' => $featureConfig];
            }

            $class = $featureConfig['class'] ?? null;
            if (!$class) {
                throw new RuntimeException("Feature configuration missing 'class' key");
            }

            if (!class_exists($class)) {
                throw new RuntimeException("Feature class '$class' does not exist");
            }

            // Check if feature needs configuration
            $config = $featureConfig['config'] ?? [];

            // Instantiate the feature
            if ($config) {
                // If feature accepts configuration in constructor
                $feature = new $class($config);
            } else {
                $feature = new $class();
            }

            if (!$feature instanceof Feature) {
                throw new RuntimeException("Class '$class' must implement Feature interface");
            }

            $features[] = $feature;
        }

        return $features;
    }

    /**
     * Recursively transform states configuration to move initial/final markers
     * from state level to region level, and convert shorthand 'run' to 'action'
     */
    private static function transformStatesConfig(array $config): array
    {
        $result = [];

        if (isset($config['states'])) {
            $transformedStates = [];
            foreach ($config['states'] as $state) {
                $transformedState = $state;

                // If state has initial: true, set it at region level
                if (isset($state['initial']) && $state['initial'] === true) {
                    $result['initial'] = $state['name'];
                    unset($transformedState['initial']);
                }

                // If state has final: true, set it at region level
                if (isset($state['final']) && $state['final'] === true) {
                    $result['final'] = $state['name'];
                    unset($transformedState['final']);
                }

                // Transform shorthand 'run' to proper 'action' structure
                if (isset($transformedState['run'])) {
                    $transformedState['action'] = [['run' => $transformedState['run']]];
                    unset($transformedState['run']);
                }

                // Recursively transform nested regions within this state
                if (isset($transformedState['regions'])) {
                    $transformedRegions = [];
                    foreach ($transformedState['regions'] as $nestedRegion) {
                        $transformedRegions[] = self::transformStatesConfig($nestedRegion);
                    }
                    $transformedState['regions'] = $transformedRegions;
                }

                $transformedStates[] = $transformedState;
            }
            $result['states'] = $transformedStates;
        }

        return $result;
    }

    /**
     * Check if RegionLoader is already in features list
     */
    private static function hasRegionLoader(array $features): bool
    {
        foreach ($features as $feature) {
            if ($feature instanceof RegionLoader) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get bootstrap helpers for initial YAML parsing
     */
    private static function getBootstrapHelpers(): array
    {
        return [
            'php' => new Helper\PhpEvalHelper(),
            'env' => fn($name) => $_ENV[$name] ?? null,
            'constant' => fn($name) => constant($name),
        ];
    }

    /**
     * Get YAML helpers with container access
     */
    private static function getYamlHelpers(ContainerInterface $container): array
    {
        $phpHelper = new Helper\PhpEvalHelper();
        $phpHelper->setScopeVariables(['container' => $container]);

        return [
            'php' => $phpHelper,
            'get' => fn($id) => $container->get($id), // Direct container access
            'env' => fn($name) => $_ENV[$name] ?? null,
            'constant' => fn($name) => constant($name),
            'service' => fn($id) => $container->get($id),
        ];
    }

    /**
     * Run the event loop based on configuration
     */
    private static function runEventLoop(
        Region $region,
        array $config,
        ContainerInterface $container
    ): mixed {
        $maxIterations = $config['maxIterations'] ?? self::DEFAULT_MAX_ITERATIONS;
        $triggerFactory = $config['trigger'] ?? fn($iteration, $region, $container) => new #[\AllowDynamicProperties] class {
            public mixed $result = null;
        };
        $onIteration = $config['onIteration'] ?? null;

        $iteration = 0;
        $lastResult = null;

        // Allow unlimited iterations when maxIterations is 0 or -1
        while ($maxIterations <= 0 || $iteration < $maxIterations) {
            $trigger = is_callable($triggerFactory) ? $triggerFactory($iteration, $region, $container) : $triggerFactory;

            if ($onIteration && is_callable($onIteration)) {
                $onIteration($region, $trigger, $iteration);
            }

            // Check if we're in final state BEFORE triggering
            $wasFinal = $region->isFinal();

            // Trigger the region - this runs actions and may transition to final state
            $region->trigger($trigger);

            $nowFinal = $region->isFinal();

            $lastResult = $trigger;

            $iteration++;

            // If we were already in final state before this trigger, we've now
            // triggered the final state's actions, so stop
            if ($wasFinal && $nowFinal) {
                break;
            }
        }

        // Throw exception with iteration count if we hit max iterations AND region is not final
        // Skip check for unlimited execution (maxIterations <= 0)
        if ($maxIterations > 0 && $iteration >= $maxIterations && !$region->isFinal()) {
            throw new RuntimeException("Event loop reached maximum iterations ($iteration)");
        }

        return $lastResult;
    }

    /**
     * Quick factory method for creating regions from simple state arrays
     * This provides backward compatibility with existing code
     */
    public static function quickRegion(array $states, array $features = []): Region
    {
        // Clean up the array by removing null values that should use defaults
        $states = self::cleanArrayConfig($states);

        $yaml = [
            'states' => $states,
        ];

        if ($features) {
            $yaml['machine'] = ['features' => $features];
        }

        $yamlString = \Symfony\Component\Yaml\Yaml::dump($yaml, 10);

        return self::fromYaml($yamlString);
    }

    /**
     * Recursively clean array configuration by removing null values
     * that should use schema defaults
     */
    private static function cleanArrayConfig(array $config): array
    {
        $cleaned = [];

        foreach ($config as $key => $value) {
            if ($value === null) {
                // Skip null values - let schema defaults apply
                continue;
            }

            if (is_array($value)) {
                // Recursively clean nested arrays
                $cleaned[$key] = self::cleanArrayConfig($value);
            } else {
                $cleaned[$key] = $value;
            }
        }

        return $cleaned;
    }
}
