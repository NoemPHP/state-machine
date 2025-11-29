<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Integration\ConfigAccessor;

use Noem\State\Chains\EnhanceRegionBuilder;
use Noem\State\Chains\Params\BuildParams;
use Noem\State\Chains\Params\Config\ConfigAccessor;
use Noem\State\Feature\Feature;
use Noem\State\Middleware\ChainMail;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Custom config accessor for testing pattern reproducibility
 */
class CustomFeatureConfig extends ConfigAccessor
{
    /**
     * Get widgets from loader config.
     * 
     * @return array<string, mixed>
     */
    public function widgets(): array
    {
        return $this->get('loader.array.custom_feature.widgets', []);
    }

    /**
     * Check if widgets are configured.
     */
    public function hasWidgets(): bool
    {
        return $this->has('loader.array.custom_feature.widgets');
    }

    /**
     * Require specific widget or throw.
     * 
     * @throws \RuntimeException
     */
    public function requireWidget(string $name): array
    {
        return $this->require("loader.array.custom_feature.widgets.{$name}");
    }
    
    /**
     * Get max items configuration.
     */
    public function maxItems(): int
    {
        return $this->get('loader.array.custom_feature.max_items', 10);
    }
}

/**
 * Custom feature using the custom config accessor
 */
class CustomTestFeature implements Feature
{
    public array $processedWidgets = [];
    public int $maxItems = 0;
    
    public function __invoke(ChainMail $chainMail): void
    {
        $chainMail->supply()->use(
            function (EnhanceRegionBuilder $enhanceRegionBuilder) {
                $enhanceRegionBuilder->link(function (BuildParams $context, callable $next) {
                    $builder = $next($context);
                    
                    $config = $context->config(CustomFeatureConfig::class);
                    if (!$config->hasWidgets()) {
                        return $builder;
                    }
                    
                    // Process widgets using the custom accessor
                    $this->processedWidgets = $config->widgets();
                    $this->maxItems = $config->maxItems();
                    
                    return $builder;
                });
            }
        );
    }
}

/**
 * Acceptance Criterion: Custom feature can create domain-specific config accessor
 */
#[Group('config-accessor'), Group('integration')]
class CustomAccessorCreationTest extends TestCase
{
    public function testCustomAccessorCreation(): void
    {
        $builder = new RegionBuilder();
        $customFeature = new CustomTestFeature();
        $builder->enableFeatures($customFeature);
        
        $widgetsData = [
            'widget1' => ['type' => 'button', 'label' => 'Click me'],
            'widget2' => ['type' => 'input', 'label' => 'Enter text'],
        ];
        
        $region = $builder
            ->setStates('idle', 'active')
            ->build([
                'loader' => [
                    'array' => [
                        'custom_feature' => [
                            'widgets' => $widgetsData,
                            'max_items' => 25
                        ]
                    ]
                ]
            ]);
        
        // Verify custom accessor was used to access config
        $this->assertSame($widgetsData, $customFeature->processedWidgets);
        $this->assertSame(25, $customFeature->maxItems);
        $this->assertNotNull($region);
    }
    
    public function testCustomAccessorWithDefaults(): void
    {
        $builder = new RegionBuilder();
        $customFeature = new CustomTestFeature();
        $builder->enableFeatures($customFeature);
        
        $region = $builder
            ->setStates('idle', 'active')
            ->build([
                'loader' => [
                    'array' => [
                        'custom_feature' => [
                            'widgets' => [] // Empty widgets to trigger processing
                            // No max_items specified, should use default
                        ]
                    ]
                ]
            ]);

        // Verify defaults are used
        $this->assertSame([], $customFeature->processedWidgets);
        $this->assertSame(10, $customFeature->maxItems); // Default from accessor
        $this->assertNotNull($region);
    }

    public function testCustomAccessorRequireMethod(): void
    {
        $paramsArray = [
            'loader' => [
                'array' => [
                    'custom_feature' => [
                        'widgets' => [
                            'myWidget' => ['type' => 'button']
                        ]
                    ]
                ]
            ]
        ];
        $params = new BuildParams(new RegionBuilder(), $paramsArray);
        
        $config = $params->config(CustomFeatureConfig::class);
        
        // Test require() returns value when exists
        $widget = $config->requireWidget('myWidget');
        $this->assertSame(['type' => 'button'], $widget);
        
        // Test require() throws when missing
        $this->expectException(\RuntimeException::class);
        $config->requireWidget('nonExistent');
    }
}
