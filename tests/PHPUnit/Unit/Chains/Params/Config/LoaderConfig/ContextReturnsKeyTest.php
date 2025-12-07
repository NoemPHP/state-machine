<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Chains\Params\Config\LoaderConfig;

use Noem\State\Chains\Params\BuildParams;
use Noem\State\Chains\Params\Config\LoaderConfig;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: LoaderConfig.context(key) returns specific context key value
 */
#[Group('config-accessor'), Group('loader-config-accessor')]
class ContextReturnsKeyTest extends TestCase
{
    public function testContextReturnsKey(): void
    {
        $builder = new RegionBuilder();
        $resolvers = [['name' => 'test1'], ['name' => 'test2']];

        $paramsArray = [
            'loader' => [
                'array' => [
                    'context' => [
                        'resolvers' => $resolvers,
                        'otherKey' => 'otherValue'
                    ]
                ]
            ]
        ];
        $params = new BuildParams($builder, $paramsArray);

        $loaderConfig = $params->config(LoaderConfig::class);

        $this->assertSame($resolvers, $loaderConfig->context('resolvers'));
        $this->assertSame('otherValue', $loaderConfig->context('otherKey'));
    }
}
