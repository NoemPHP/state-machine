<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Chains\Params\Config\LoaderConfig;

use Noem\State\Chains\Params\BuildParams;
use Noem\State\Chains\Params\Config\LoaderConfig;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: LoaderConfig.states() returns state definitions array
 */
#[Group('config-accessor'), Group('loader-config-accessor')]
class StatesReturnsArrayTest extends TestCase
{
    public function testStatesReturnsArray(): void
    {
        $builder = new RegionBuilder();
        $paramsArray = [
            'loader' => [
                'array' => [
                    'states' => ['idle', 'active', 'done']
                ]
            ]
        ];
        $params = new BuildParams($builder, $paramsArray);
        
        $loaderConfig = $params->config(LoaderConfig::class);
        
        $this->assertSame(['idle', 'active', 'done'], $loaderConfig->states());
    }
}
