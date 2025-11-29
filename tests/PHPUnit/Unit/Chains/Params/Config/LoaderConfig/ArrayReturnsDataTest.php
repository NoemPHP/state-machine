<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Chains\Params\Config\LoaderConfig;

use Noem\State\Chains\Params\BuildParams;
use Noem\State\Chains\Params\Config\LoaderConfig;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: LoaderConfig.array() returns full loader array data
 */
#[Group('config-accessor'), Group('loader-config-accessor')]
class ArrayReturnsDataTest extends TestCase
{
    public function testArrayReturnsData(): void
    {
        $builder = new RegionBuilder();
        $loaderData = [
            'states' => ['a', 'b'],
            'context' => ['key' => 'value']
        ];
        
        $config = [
            'loader' => [
                'array' => $loaderData
            ]
        ];

        $params = new BuildParams($builder, $config);
        
        $loaderConfig = $params->config(LoaderConfig::class);
        
        $this->assertSame($loaderData, $loaderConfig->array());
    }
}
