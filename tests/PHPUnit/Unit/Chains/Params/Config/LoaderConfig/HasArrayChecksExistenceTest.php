<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Chains\Params\Config\LoaderConfig;

use Noem\State\Chains\Params\BuildParams;
use Noem\State\Chains\Params\Config\LoaderConfig;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: LoaderConfig.hasArray() checks if array loader is being used
 */
#[Group('config-accessor'), Group('loader-config-accessor')]
class HasArrayChecksExistenceTest extends TestCase
{
    public function testHasArrayReturnsTrueWhenArrayExists(): void
    {
        $builder = new RegionBuilder();
        $config = [
            'loader' => [
                'array' => ['states' => []]
            ]
        ];
        $params = new BuildParams($builder, $config);

        $loaderConfig = $params->config(LoaderConfig::class);

        $this->assertTrue($loaderConfig->hasArray());
    }

    public function testHasArrayReturnsFalseWhenArrayMissing(): void
    {
        $builder = new RegionBuilder();
        $config = ['loader' => []];
        $params = new BuildParams($builder, $config);

        $loaderConfig = $params->config(LoaderConfig::class);

        $this->assertFalse($loaderConfig->hasArray());
    }
}
