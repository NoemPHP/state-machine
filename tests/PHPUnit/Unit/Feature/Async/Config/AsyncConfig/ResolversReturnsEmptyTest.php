<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\Config\AsyncConfig;

use Noem\State\Chains\Params\BuildParams;
use Noem\State\Feature\Async\Config\AsyncConfig;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: AsyncConfig.resolvers() returns empty array when resolvers not defined
 */
#[Group('config-accessor'), Group('async-config-accessor')]
class ResolversReturnsEmptyTest extends TestCase
{
    public function testResolversReturnsEmptyWhenNotDefined(): void
    {
        $builder = new RegionBuilder();

        $paramsArray = [
            'loader' => [
                'array' => [
                    'context' => [
                        'otherKey' => 'value'
                    ]
                ]
            ]
        ];
        $params = new BuildParams($builder, $paramsArray);

        $asyncConfig = $params->config(AsyncConfig::class);

        $this->assertSame([], $asyncConfig->resolvers());
    }

    public function testResolversReturnsEmptyWhenContextMissing(): void
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

        $asyncConfig = $params->config(AsyncConfig::class);

        $this->assertSame([], $asyncConfig->resolvers());
    }
}
