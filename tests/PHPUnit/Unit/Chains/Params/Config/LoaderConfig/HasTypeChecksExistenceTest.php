<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Chains\Params\Config\LoaderConfig;

use Noem\State\Chains\Params\BuildParams;
use Noem\State\Chains\Params\Config\LoaderConfig;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: LoaderConfig.hasType() checks if specific loader type exists
 */
#[Group('config-accessor'), Group('loader-config-accessor')]
class HasTypeChecksExistenceTest extends TestCase
{
    public function testHasTypeChecksExistence(): void
    {
        $builder = new RegionBuilder();

        $paramsArray = [
            'loader' => [
                'array' => ['states' => ['a']],
                'loaderConfig' => ['version' => '1.0'],
                'customType' => ['data' => 'value']
            ]
        ];
        $params = new BuildParams($builder, $paramsArray);

        $loaderConfig = $params->config(LoaderConfig::class);

        $this->assertTrue($loaderConfig->hasType('array'));
        $this->assertTrue($loaderConfig->hasType('loaderConfig'));
        $this->assertTrue($loaderConfig->hasType('customType'));
        $this->assertFalse($loaderConfig->hasType('nonExistent'));
    }
}
