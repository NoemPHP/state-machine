<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Chains\Params\Config\LoaderConfig;

use Noem\State\Chains\Params\BuildParams;
use Noem\State\Chains\Params\Config\LoaderConfig;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: LoaderConfig.context() returns full context when key is null
 */
#[Group('config-accessor'), Group('loader-config-accessor')]
class ContextReturnsFullTest extends TestCase
{
    public function testContextReturnsFull(): void
    {
        $builder = new RegionBuilder();
        $contextData = [
            'resolvers' => [['name' => 'test']],
            'variables' => ['key' => 'value'],
            'customData' => 'test'
        ];
        
        $paramsArray = [
            'loader' => [
                'array' => [
                    'context' => $contextData
                ]
            ]
        ];
        $params = new BuildParams($builder, $paramsArray);
        
        $loaderConfig = $params->config(LoaderConfig::class);
        
        $this->assertSame($contextData, $loaderConfig->context());
    }
}
