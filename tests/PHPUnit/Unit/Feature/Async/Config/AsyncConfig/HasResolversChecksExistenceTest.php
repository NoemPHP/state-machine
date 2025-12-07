<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\Config\AsyncConfig;

use Noem\State\Chains\Params\BuildParams;
use Noem\State\Feature\Async\Config\AsyncConfig;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: AsyncConfig.hasResolvers() checks if any resolvers are defined
 */
#[Group('config-accessor'), Group('async-config-accessor')]
class HasResolversChecksExistenceTest extends TestCase
{
    public function testHasResolversReturnsTrueWhenDefined(): void
    {
        $builder = new RegionBuilder();

        $paramsArray = [
            'loader' => [
                'array' => [
                    'context' => [
                        'resolvers' => [
                            ['name' => 'test', 'run' => fn() => 'value']
                        ]
                    ]
                ]
            ]
        ];
        $params = new BuildParams($builder, $paramsArray);

        $asyncConfig = $params->config(AsyncConfig::class);

        $this->assertTrue($asyncConfig->hasResolvers());
    }

    public function testHasResolversReturnsFalseWhenEmpty(): void
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

        $this->assertFalse($asyncConfig->hasResolvers());
    }

    public function testHasResolversReturnsFalseWhenNotDefined(): void
    {
        $builder = new RegionBuilder();

        $paramsArray = [
            'loader' => [
                'array' => [
                    'context' => []
                ]
            ]
        ];
        $params = new BuildParams($builder, $paramsArray);

        $asyncConfig = $params->config(AsyncConfig::class);

        $this->assertFalse($asyncConfig->hasResolvers());
    }
}
