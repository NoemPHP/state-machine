<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\Config\AsyncConfig;

use Noem\State\Chains\Params\BuildParams;
use Noem\State\Feature\Async\Config\AsyncConfig;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: AsyncConfig.resolver(name) returns null when resolver not found
 */
#[Group('config-accessor'), Group('async-config-accessor')]
class ResolverReturnsNullTest extends TestCase
{
    public function testResolverReturnsNullWhenNotFound(): void
    {
        $builder = new RegionBuilder();
        
        $paramsArray = [
            'loader' => [
                'array' => [
                    'context' => [
                        'resolvers' => [
                            ['name' => 'first', 'run' => fn() => 'first'],
                            ['name' => 'second', 'run' => fn() => 'second'],
                        ]
                    ]
                ]
            ]
        ];
        $params = new BuildParams($builder, $paramsArray);

        $asyncConfig = $params->config(AsyncConfig::class);

        $this->assertNull($asyncConfig->resolver('nonExistent'));
        $this->assertNull($asyncConfig->resolver('third'));
    }

    public function testResolverReturnsNullWhenNoResolvers(): void
    {
        $builder = new RegionBuilder();

        $paramsArray = [
            'loader' => [
                'array' => [
                    'context' => [
                        'resolvers' => []
                    ]
                ]
            ]
        ];
        $params = new BuildParams($builder, $paramsArray);
        
        $asyncConfig = $params->config(AsyncConfig::class);
        
        $this->assertNull($asyncConfig->resolver('anyName'));
    }
}
