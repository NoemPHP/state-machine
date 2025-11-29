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
 * Config accessor with initialize() hook for caching
 */
class CachingConfigAccessor extends ConfigAccessor
{
    private array $cache = [];
    public bool $initializeCalled = false;
    
    protected function initialize(): void
    {
        $this->initializeCalled = true;
        
        // Build cache during initialization
        $items = $this->get('loader.array.items', []);
        foreach ($items as $item) {
            if (isset($item['id'])) {
                $this->cache[$item['id']] = $item;
            }
        }
    }
    
    /**
     * Get item by ID from cache (built during initialization)
     */
    public function getItemById(string $id): ?array
    {
        return $this->cache[$id] ?? null;
    }
    
    /**
     * Get all cached items
     */
    public function getCachedItems(): array
    {
        return $this->cache;
    }
}

/**
 * Config accessor with validation in initialize()
 */
class ValidatingConfigAccessor extends ConfigAccessor
{
    public bool $isValid = false;
    public array $validationErrors = [];
    
    protected function initialize(): void
    {
        // Perform validation during initialization
        $this->validationErrors = [];
        
        if (!$this->has('loader.array.required_field')) {
            $this->validationErrors[] = 'required_field is missing';
        }
        
        $version = $this->get('loader.array.version');
        if ($version !== null && !is_string($version)) {
            $this->validationErrors[] = 'version must be a string';
        }
        
        $this->isValid = empty($this->validationErrors);
    }
    
    public function isValid(): bool
    {
        return $this->isValid;
    }
    
    public function getValidationErrors(): array
    {
        return $this->validationErrors;
    }
}

/**
 * Feature using caching accessor
 */
class CachingTestFeature implements Feature
{
    public ?array $foundItem = null;
    
    public function __invoke(ChainMail $chainMail): void
    {
        $chainMail->supply()->use(
            function (EnhanceRegionBuilder $enhanceRegionBuilder) {
                $enhanceRegionBuilder->link(function (BuildParams $context, callable $next) {
                    $builder = $next($context);
                    
                    $config = $context->config(CachingConfigAccessor::class);
                    // Use cached lookup instead of searching through array
                    $this->foundItem = $config->getItemById('item2');
                    
                    return $builder;
                });
            }
        );
    }
}

/**
 * Acceptance Criterion: Accessor with initialize() hook runs custom setup logic
 */
#[Group('config-accessor'), Group('integration')]
class InitializeHookTest extends TestCase
{
    public function testInitializeHookRunsOnCreation(): void
    {
        $configArray = [
            'loader' => [
                'array' => [
                    'items' => []
                ]
            ]
        ];
        $params = new BuildParams(new RegionBuilder(), $configArray);
        
        $accessor = $params->config(CachingConfigAccessor::class);
        
        // Verify initialize() was called
        $this->assertTrue($accessor->initializeCalled);
    }
    
    public function testInitializeHookBuildsCache(): void
    {
        $items = [
            ['id' => 'item1', 'name' => 'First'],
            ['id' => 'item2', 'name' => 'Second'],
            ['id' => 'item3', 'name' => 'Third'],
        ];

        $configArray = [
            'loader' => [
                'array' => [
                    'items' => $items
                ]
            ]
        ];
        $params = new BuildParams(new RegionBuilder(), $configArray);
        
        $accessor = $params->config(CachingConfigAccessor::class);
        
        // Verify cache was built during initialization
        $cached = $accessor->getCachedItems();
        $this->assertCount(3, $cached);
        $this->assertSame('First', $cached['item1']['name']);
        $this->assertSame('Second', $cached['item2']['name']);
        
        // Verify cached lookup works
        $item = $accessor->getItemById('item2');
        $this->assertSame('Second', $item['name']);
    }
    
    public function testInitializeHookWithValidation(): void
    {
        $configArrayValid = [
            'loader' => [
                'array' => [
                    'required_field' => 'present',
                    'version' => '1.0'
                ]
            ]
        ];
        $paramsValid = new BuildParams(new RegionBuilder(), $configArrayValid);

        $configArrayInvalid = [
            'loader' => [
                'array' => [
                    'version' => 123 // Invalid type
                ]
            ]
        ];
        $paramsInvalid = new BuildParams(new RegionBuilder(), $configArrayInvalid);
        
        $accessorValid = $paramsValid->config(ValidatingConfigAccessor::class);
        $accessorInvalid = $paramsInvalid->config(ValidatingConfigAccessor::class);
        
        // Verify validation ran during initialization
        $this->assertTrue($accessorValid->isValid());
        $this->assertEmpty($accessorValid->getValidationErrors());
        
        $this->assertFalse($accessorInvalid->isValid());
        $this->assertContains('required_field is missing', $accessorInvalid->getValidationErrors());
        $this->assertContains('version must be a string', $accessorInvalid->getValidationErrors());
    }
    
    public function testFeatureUsesInitializedCache(): void
    {
        $builder = new RegionBuilder();
        $feature = new CachingTestFeature();
        $builder->enableFeatures($feature);
        
        $items = [
            ['id' => 'item1', 'name' => 'First'],
            ['id' => 'item2', 'name' => 'Second'],
            ['id' => 'item3', 'name' => 'Third'],
        ];
        
        $region = $builder
            ->setStates('idle')
            ->build([
                'loader' => [
                    'array' => [
                        'items' => $items
                    ]
                ]
            ]);
        
        // Verify feature used the cached lookup from initialize()
        $this->assertNotNull($feature->foundItem);
        $this->assertSame('item2', $feature->foundItem['id']);
        $this->assertSame('Second', $feature->foundItem['name']);
        $this->assertNotNull($region);
    }
}
