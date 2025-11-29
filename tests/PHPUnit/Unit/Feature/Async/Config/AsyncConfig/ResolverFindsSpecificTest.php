<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\Config\AsyncConfig;

use Noem\State\Chains\Params\BuildParams;
use Noem\State\Feature\Async\Config\AsyncConfig;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: AsyncConfig.resolver(name) finds specific resolver by name
 */
#[Group('config-accessor'), Group('async-config-accessor')]
class ResolverFindsSpecificTest extends TestCase
{
    public function testResolverFindsSpecific(): void
    {
        $builder = new RegionBuilder();
        $firstResolver = fn() => 'first';
        $secondResolver = fn() => 'second';
        
        $paramsArray = [
            'loader' => [
                'array' => [
                    'context' => [
                        'resolvers' => [
                            ['name' => 'first', 'run' => $firstResolver],
                            ['name' => 'second', 'run' => $secondResolver],
                            ['name' => 'third', 'run' => fn() => 'third'],
                        ]
                    ]
                ]
            ]
        ];
        $params = new BuildParams($builder, $paramsArray);

        $asyncConfig = $params->config(AsyncConfig::class);

        $found = $asyncConfig->resolver('second');
        $this->assertNotNull($found);
        $this->assertSame('second', $found['name']);
        $this->assertSame($secondResolver, $found['run']);
    }

    public function testResolverFindsFirst(): void
    {
        $builder = new RegionBuilder();
        $resolver = fn() => 'value';

        $paramsArray = [
            'loader' => [
                'array' => [
                    'context' => [
                        'resolvers' => [
                            ['name' => 'target', 'run' => $resolver],
                        ]
                    ]
                ]
            ]
        ];
        $params = new BuildParams($builder, $paramsArray);
        
        $asyncConfig = $params->config(AsyncConfig::class);
        
        $found = $asyncConfig->resolver('target');
        $this->assertNotNull($found);
        $this->assertSame('target', $found['name']);
        $this->assertSame($resolver, $found['run']);
    }
}
