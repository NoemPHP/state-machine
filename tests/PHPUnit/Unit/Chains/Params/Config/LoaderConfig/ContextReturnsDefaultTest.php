<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Chains\Params\Config\LoaderConfig;

use Noem\State\Chains\Params\BuildParams;
use Noem\State\Chains\Params\Config\LoaderConfig;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: LoaderConfig.context() returns default when context missing
 */
#[Group('config-accessor'), Group('loader-config-accessor')]
class ContextReturnsDefaultTest extends TestCase
{
    public function testContextReturnsDefaultWhenMissing(): void
    {
        $builder = new RegionBuilder();
        
        $paramsArray = [
            'loader' => [
                'array' => [
                    'states' => ['a', 'b']
                ]
            ]
        ];
        $params = new BuildParams($builder, $paramsArray);
        
        $loaderConfig = $params->config(LoaderConfig::class);
        
        // Test missing context returns null by default
        $this->assertNull($loaderConfig->context());
        
        // Test missing context returns custom default
        $this->assertSame([], $loaderConfig->context(default: []));
        $this->assertSame('default', $loaderConfig->context(default: 'default'));
    }
    
    public function testContextReturnsDefaultForMissingKey(): void
    {
        $builder = new RegionBuilder();
        
        $paramsArray = [
            'loader' => [
                'array' => [
                    'context' => [
                        'existingKey' => 'value'
                    ]
                ]
            ]
        ];
        $params = new BuildParams($builder, $paramsArray);
        
        $loaderConfig = $params->config(LoaderConfig::class);
        
        // Test missing key returns null by default
        $this->assertNull($loaderConfig->context('missingKey'));
        
        // Test missing key returns custom default
        $this->assertSame([], $loaderConfig->context('missingKey', []));
        $this->assertSame('fallback', $loaderConfig->context('missingKey', 'fallback'));
    }
}
