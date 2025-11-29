<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Chains\Params\Config\LoaderConfig;

use Noem\State\Chains\Params\BuildParams;
use Noem\State\Chains\Params\Config\LoaderConfig;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: LoaderConfig.hasStates() checks if states are defined
 */
#[Group('config-accessor'), Group('loader-config-accessor')]
class HasStatesChecksExistenceTest extends TestCase
{
    public function testHasStatesReturnsTrueWhenStatesDefined(): void
    {
        $builder = new RegionBuilder();
        $config = [
            'loader' => [
                'array' => [
                    'states' => ['a', 'b']
                ]
            ]
        ];
        $params = new BuildParams($builder, $config);
        
        $loaderConfig = $params->config(LoaderConfig::class);
        
        $this->assertTrue($loaderConfig->hasStates());
    }
    
    public function testHasStatesReturnsFalseWhenStatesNotDefined(): void
    {
        $builder = new RegionBuilder();
        $config = ['loader' => ['array' => []]];
        $params = new BuildParams($builder, $config);
        
        $loaderConfig = $params->config(LoaderConfig::class);
        
        $this->assertFalse($loaderConfig->hasStates());
    }
}
