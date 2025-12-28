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
 * SelfContainedLoader enables bootstrapping complete state machines from a single YAML file.
 *
 * This solves the chicken/egg problem by using a two-phase bootstrap:
 * 1. Phase 1: Parse machine configuration and setup container
 * 2. Phase 2: Build the actual state machine with all features
 */
class SelfContainedLoader
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
        $converter = new ConvertYaml();
        $config = $converter->fromString($yaml, self::getBootstrapHelpers());

        // Extract machine configuration
        $machineConfig = $config['machine'] ?? [];
        $statesConfig = $config['states'] ?? [];
        $regionsConfig = $config['regions'] ?? [];

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

        // Build the region with the states configuration
        $builderArgs = [
            'loader' => [
                'yaml' => $yaml,
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

        if ($eventLoopConfig['autoRun'] ?? false) {
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
                // Factory-based service
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
                    if (is_callable($factory)) {
                        $this->resolved[$id] = $factory($this);
                        return $this->resolved[$id];
                    }
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
        return [
            'php' => new Helper\PhpEvalHelper(),
            'get' => new Helper\ContainerGetHelper($container),
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
        $triggerFactory = $config['trigger'] ?? fn() => new \stdClass();
        $onIteration = $config['onIteration'] ?? null;

        $iteration = 0;
        $lastResult = null;

        // Allow unlimited iterations when maxIterations is 0 or -1
        while (!$region->isFinal() && ($maxIterations <= 0 || $iteration < $maxIterations)) {
            $trigger = is_callable($triggerFactory) ? $triggerFactory($iteration, $region, $container) : $triggerFactory;

            if ($onIteration && is_callable($onIteration)) {
                $onIteration($region, $trigger, $iteration);
            }

            $lastResult = $region->trigger($trigger);
            $iteration++;
        }

        // Skip check for unlimited execution (maxIterations <= 0)
        if ($maxIterations > 0 && $iteration >= $maxIterations) {
            throw new RuntimeException("Event loop reached maximum iterations ($maxIterations)");
        }

        return $lastResult;
    }

    /**
     * Quick factory method for creating regions from simple state arrays
     * This provides backward compatibility with existing code
     */
    public static function quickRegion(array $states, array $features = []): Region
    {
        $yaml = [
            'states' => $states,
        ];

        if ($features) {
            $yaml['machine'] = ['features' => $features];
        }

        $yamlString = \Symfony\Component\Yaml\Yaml::dump($yaml, 10);

        return self::fromYaml($yamlString);
    }
}
