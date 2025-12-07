<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\Config\AsyncConfig;

use Noem\State\Chains\Params\BuildParams;
use Noem\State\Feature\Async\Config\AsyncConfig;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: AsyncConfig.resolvers() returns resolver definitions array
 */
#[Group('config-accessor'), Group('async-config-accessor')]
class ResolversReturnsArrayTest extends TestCase
{
    public function testResolversReturnsArray(): void
    {
        $builder = new RegionBuilder();
        $resolver1 = fn() => 'value1';
        $resolver2 = fn() => 'value2';

        $paramsArray = [
            'loader' => [
                'array' => [
                    'context' => [
                        'resolvers' => [
                            ['name' => 'first', 'run' => $resolver1],
                            ['name' => 'second', 'run' => $resolver2],
                        ]
                    ]
                ]
            ]
        ];
        $params = new BuildParams($builder, $paramsArray);

        $asyncConfig = $params->config(AsyncConfig::class);

        $resolvers = $asyncConfig->resolvers();
        $this->assertCount(2, $resolvers);
        $this->assertSame('first', $resolvers[0]['name']);
        $this->assertSame('second', $resolvers[1]['name']);
    }
}
