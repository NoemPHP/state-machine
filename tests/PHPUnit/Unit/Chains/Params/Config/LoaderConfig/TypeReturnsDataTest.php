<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Chains\Params\Config\LoaderConfig;

use Noem\State\Chains\Params\BuildParams;
use Noem\State\Chains\Params\Config\LoaderConfig;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: LoaderConfig.type() returns data for specific loader type
 */
#[Group('config-accessor'), Group('loader-config-accessor')]
class TypeReturnsDataTest extends TestCase
{
    public function testTypeReturnsData(): void
    {
        $builder = new RegionBuilder();
        $arrayData = ['states' => ['a', 'b']];
        $configData = ['version' => '1.0'];
        $customData = ['custom' => 'value'];

        $paramsArray = [
            'loader' => [
                'array' => $arrayData,
                'loaderConfig' => $configData,
                'customType' => $customData
            ]
        ];
        $params = new BuildParams($builder, $paramsArray);

        $loaderConfig = $params->config(LoaderConfig::class);

        $this->assertSame($arrayData, $loaderConfig->type('array'));
        $this->assertSame($configData, $loaderConfig->type('loaderConfig'));
        $this->assertSame($customData, $loaderConfig->type('customType'));
    }

    public function testTypeReturnsDefaultWhenMissing(): void
    {
        $builder = new RegionBuilder();

        $paramsArray = [
            'loader' => [
                'array' => ['states' => ['a']]
            ]
        ];
        $params = new BuildParams($builder, $paramsArray);

        $loaderConfig = $params->config(LoaderConfig::class);

        $this->assertNull($loaderConfig->type('nonExistent'));
        $this->assertSame([], $loaderConfig->type('nonExistent', []));
        $this->assertSame('default', $loaderConfig->type('nonExistent', 'default'));
    }
}
