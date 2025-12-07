<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Chains\Params\Config\LoaderConfig;

use Noem\State\Chains\Params\BuildParams;
use Noem\State\Chains\Params\Config\LoaderConfig;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: LoaderConfig.loaderConfig() returns loader metadata
 */
#[Group('config-accessor'), Group('loader-config-accessor')]
class LoaderConfigReturnsMetadataTest extends TestCase
{
    public function testLoaderConfigReturnsMetadata(): void
    {
        $builder = new RegionBuilder();
        $metadata = [
            'version' => '1.0',
            'schema' => 'custom-schema',
            'options' => ['strict' => true]
        ];

        $paramsArray = [
            'loader' => [
                'loaderConfig' => $metadata
            ]
        ];
        $params = new BuildParams($builder, $paramsArray);

        $loaderConfig = $params->config(LoaderConfig::class);

        $this->assertSame($metadata, $loaderConfig->loaderConfig());
    }

    public function testLoaderConfigReturnsNullWhenNotSet(): void
    {
        $builder = new RegionBuilder();

        $paramsArray = [
            'loader' => [
                'array' => ['states' => ['a']]
            ]
        ];
        $params = new BuildParams($builder, $paramsArray);

        $loaderConfig = $params->config(LoaderConfig::class);

        $this->assertNull($loaderConfig->loaderConfig());
    }
}
