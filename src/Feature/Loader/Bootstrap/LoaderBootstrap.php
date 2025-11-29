<?php

declare(strict_types=1);

namespace Noem\State\Feature\Loader\Bootstrap;

use Noem\State\Feature\Feature;
use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\Middleware\ChainMail;
use Noem\State\RegionBuilder;

/**
 * LoaderBootstrap solves the chicken/egg problem of RegionLoader configuring itself.
 * 
 * This uses a two-phase approach:
 * 1. Phase 1: Minimal bootstrap to parse loader configuration
 * 2. Phase 2: Full RegionLoader with custom configuration applied
 * 
 * This allows the loader configuration in YAML to affect how the loader itself works.
 */
class LoaderBootstrap
{
    /**
     * Create a configured RegionLoader from loader configuration
     * 
     * @param array $loaderConfig Configuration from machine.loader in YAML
     * @return RegionLoader Configured loader instance
     */
    public static function createConfiguredLoader(array $loaderConfig): RegionLoader
    {
        // If there's no custom loader config, return standard loader
        if (empty($loaderConfig)) {
            return new RegionLoader();
        }
        
        // Create a custom loader that applies configuration
        return new class($loaderConfig) extends RegionLoader {
            private array $config;
            
            public function __construct(array $config)
            {
                $this->config = $config;
            }
            
            public function __invoke(ChainMail $chainMail): void
            {
                // First, run the parent's standard setup
                parent::__invoke($chainMail);
                
                // Then apply custom configuration
                $this->applyCustomConfiguration($chainMail);
            }
            
            private function applyCustomConfiguration(ChainMail $chainMail): void
            {
                // Apply schema extensions if defined
                if (isset($this->config['schemaExtensions'])) {
                    $this->applySchemaExtensions($chainMail, $this->config['schemaExtensions']);
                }
                
                // Apply custom helpers if defined
                if (isset($this->config['helpers'])) {
                    $this->applyCustomHelpers($chainMail, $this->config['helpers']);
                }
                
                // Apply transforms if defined
                if (isset($this->config['transforms'])) {
                    $this->applyTransforms($chainMail, $this->config['transforms']);
                }
                
                // Apply validation settings if defined
                if (isset($this->config['validation'])) {
                    $this->applyValidationSettings($chainMail, $this->config['validation']);
                }
            }
            
            private function applySchemaExtensions(ChainMail $chainMail, array $extensions): void
            {
                $chainMail->use(function($context, $next) use ($extensions) {
                    // Extensions would modify the Schema chain
                    // This is where custom state/region schemas would be added
                    foreach ($extensions as $extension) {
                        // Process and apply schema extension
                        // This would interact with LoaderChains\Schema
                    }
                    return $next($context);
                });
            }
            
            private function applyCustomHelpers(ChainMail $chainMail, array $helpers): void
            {
                // These helpers become available in YAML processing
                $chainMail->supply(function() use ($helpers): array {
                    return $helpers;
                });
            }
            
            private function applyTransforms(ChainMail $chainMail, array $transforms): void
            {
                foreach ($transforms as $transform) {
                    if (is_array($transform) && isset($transform['class'])) {
                        $class = $transform['class'];
                        $config = $transform['config'] ?? [];
                        
                        if (class_exists($class)) {
                            $instance = new $class($config);
                            
                            // Apply the transform to the chain
                            $chainMail->use(function($context, $next) use ($instance) {
                                // Transform would modify the processing pipeline
                                return $next($context);
                            });
                        }
                    }
                }
            }
            
            private function applyValidationSettings(ChainMail $chainMail, array $settings): void
            {
                $chainMail->supply(function() use ($settings): object {
                    return (object)$settings;
                });
            }
        };
    }
    
    /**
     * Bootstrap a RegionBuilder with loader configuration
     * This demonstrates the two-phase approach
     */
    public static function bootstrap(array $machineConfig): RegionBuilder
    {
        // Phase 1: Extract loader configuration
        $loaderConfig = $machineConfig['loader'] ?? [];
        
        // Phase 2: Create configured loader
        $configuredLoader = self::createConfiguredLoader($loaderConfig);
        
        // Create builder with configured loader
        $builder = new RegionBuilder();
        $builder->enableFeatures($configuredLoader);
        
        // Apply other features
        if (isset($machineConfig['features'])) {
            foreach ($machineConfig['features'] as $featureConfig) {
                // Instantiate and add features
                // (This would use the same logic as SelfContainedLoader::instantiateFeatures)
            }
        }
        
        return $builder;
    }
    
    /**
     * Demonstrate self-referential configuration
     * The loader can modify its own behavior based on configuration
     */
    public static function createSelfConfiguringLoader(): Feature
    {
        return new class implements Feature {
            public function __invoke(ChainMail $chainMail): void
            {
                // This loader can inspect its own configuration and modify behavior
                $chainMail->use(function($context, $next) {
                    // If we're loading a RegionLoader configuration
                    $loaderConfig = $context->config(\Noem\State\Chains\Params\Config\LoaderConfig::class);
                    if ($loaderConfig->hasType('loaderConfig')) {
                        $config = $loaderConfig->loaderConfig();
                        
                        // Create a new loader with this configuration
                        $newLoader = LoaderBootstrap::createConfiguredLoader($config);
                        
                        // Replace ourselves with the new configured loader
                        $context['chainMail']->supply(fn(): RegionLoader => $newLoader);
                    }
                    
                    return $next($context);
                });
            }
        };
    }
}
