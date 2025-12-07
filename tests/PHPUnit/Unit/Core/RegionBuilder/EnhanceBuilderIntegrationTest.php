<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Core\RegionBuilder;

use Noem\State\Chains\EnhanceRegionBuilder;
use Noem\State\Chains\Params\BuildParams;
use Noem\State\Region;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Builder integrates with EnhanceRegionBuilder chain for middleware processing
 */
#[Group('region-builder')]
#[Group('chainmail-integration')]
class EnhanceBuilderIntegrationTest extends TestCase
{
    public function testBuilderUsesEnhanceRegionBuilderChain(): void
    {
        $builder = new RegionBuilder();
        $builder->setStates('idle');

        $enhanceChainCalled = false;

        $builder->chainMail->use(function (EnhanceRegionBuilder $enhance) use (&$enhanceChainCalled) {
            $enhance->link(function (BuildParams $params, callable $next) use (&$enhanceChainCalled) {
                $enhanceChainCalled = true;
                return $next($params);
            });
        });

        $builder->build();

        $this->assertTrue($enhanceChainCalled, 'EnhanceRegionBuilder chain should be invoked during build');
    }

    public function testEnhanceChainReceivesBuildParams(): void
    {
        $builder = new RegionBuilder();
        $builder->setStates('idle');

        $receivedParams = null;

        $builder->chainMail->use(function (EnhanceRegionBuilder $enhance) use (&$receivedParams) {
            $enhance->link(function (BuildParams $params, callable $next) use (&$receivedParams) {
                $receivedParams = $params;
                return $next($params);
            });
        });

        $builder->build();

        $this->assertInstanceOf(
            BuildParams::class,
            $receivedParams,
            'EnhanceRegionBuilder chain should receive BuildParams'
        );
    }

    public function testEnhanceChainCanModifyBuilder(): void
    {
        $builder = new RegionBuilder();
        $builder->setStates('idle');

        $builder->chainMail->use(function (EnhanceRegionBuilder $enhance) {
            $enhance->link(function (BuildParams $params, callable $next) {
                $modifiedBuilder = $next($params);
                $this->assertInstanceOf(RegionBuilder::class, $modifiedBuilder);
                return $modifiedBuilder;
            });
        });

        $region = $builder->build();

        $this->assertInstanceOf(Region::class, $region);
    }

    public function testEnhanceChainMiddlewareExecutesBeforeBuildSteps(): void
    {
        $builder = new RegionBuilder();
        $builder->setStates('idle');

        $executionOrder = [];

        $builder->chainMail->use(function (EnhanceRegionBuilder $enhance) use (&$executionOrder) {
            $enhance->link(function (BuildParams $params, callable $next) use (&$executionOrder) {
                $executionOrder[] = 'enhance';
                return $next($params);
            });
        });

        $builder->addBuildStep(new class ($executionOrder) implements \Noem\State\BuildStep {
            public function __construct(private array &$order)
            {
            }

            public function callback(RegionBuilder $builder, callable $next, callable $first): Region
            {
                $this->order[] = 'build_step';
                return $next($builder);
            }
        });

        $builder->build();

        $this->assertEquals(
            ['enhance', 'build_step'],
            $executionOrder,
            'EnhanceRegionBuilder should execute before build steps'
        );
    }
}
