<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\Config\AsyncConfig;

use Noem\State\Chains\Params\BuildParams;
use Noem\State\Feature\Async\Config\AsyncConfig;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: AsyncConfig.hasResolver(name) checks if specific resolver exists
 */
#[Group('config-accessor'), Group('async-config-accessor')]
class HasResolverChecksSpecificTest extends TestCase
{
    public function testHasResolverReturnsTrueWhenExists(): void
    {
        $builder = new RegionBuilder();
        
        $paramsArray = [
            'loader' => [
                'array' => [
                    'context' => [
                        'resolvers' => [
                            ['name' => 'first', 'run' => fn() => 'first'],
                            ['name' => 'second', 'run' => fn() => 'second'],
                            ['name' => 'third', 'run' => fn() => 'third'],
                        ]
                    ]
                ]
            ]
        ];
        $params = new BuildParams($builder, $paramsArray);

        $asyncConfig = $params->config(AsyncConfig::class);

        $this->assertTrue($asyncConfig->hasResolver('first'));
        $this->assertTrue($asyncConfig->hasResolver('second'));
        $this->assertTrue($asyncConfig->hasResolver('third'));
    }

    public function testHasResolverReturnsFalseWhenNotFound(): void
    {
        $builder = new RegionBuilder();

        $paramsArray = [
            'loader' => [
                'array' => [
                    'context' => [
                        'resolvers' => [
                            ['name' => 'exists', 'run' => fn() => 'value'],
                        ]
                    ]
                ]
            ]
        ];
        $params = new BuildParams($builder, $paramsArray);
        
        $asyncConfig = $params->config(AsyncConfig::class);
        
        $this->assertFalse($asyncConfig->hasResolver('nonExistent'));
        $this->assertFalse($asyncConfig->hasResolver('missing'));
    }
}
