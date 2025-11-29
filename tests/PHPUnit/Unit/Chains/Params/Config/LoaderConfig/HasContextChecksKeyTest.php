<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Chains\Params\Config\LoaderConfig;

use Noem\State\Chains\Params\BuildParams;
use Noem\State\Chains\Params\Config\LoaderConfig;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: LoaderConfig.hasContext() checks if context exists for given key
 */
#[Group('config-accessor'), Group('loader-config-accessor')]
class HasContextChecksKeyTest extends TestCase
{
    public function testHasContextChecksFullContext(): void
    {
        $builder = new RegionBuilder();
        
        $paramsArrayWith = [
            'loader' => [
                'array' => [
                    'context' => ['key' => 'value']
                ]
            ]
        ];
        $paramsWithContext = new BuildParams($builder, $paramsArrayWith);

        $paramsArrayWithout = [
            'loader' => [
                'array' => [
                    'states' => ['a', 'b']
                ]
            ]
        ];
        $paramsWithoutContext = new BuildParams($builder, $paramsArrayWithout);
        
        $configWith = $paramsWithContext->config(LoaderConfig::class);
        $configWithout = $paramsWithoutContext->config(LoaderConfig::class);
        
        $this->assertTrue($configWith->hasContext());
        $this->assertFalse($configWithout->hasContext());
    }
    
    public function testHasContextChecksSpecificKey(): void
    {
        $builder = new RegionBuilder();
        
        $paramsArray = [
            'loader' => [
                'array' => [
                    'context' => [
                        'resolvers' => [],
                        'variables' => []
                    ]
                ]
            ]
        ];
        $params = new BuildParams($builder, $paramsArray);
        
        $loaderConfig = $params->config(LoaderConfig::class);
        
        $this->assertTrue($loaderConfig->hasContext('resolvers'));
        $this->assertTrue($loaderConfig->hasContext('variables'));
        $this->assertFalse($loaderConfig->hasContext('nonExistent'));
    }
}
