<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit;

use Noem\State\Chains\DispatchAction;
use Noem\State\Chains\EnhanceRegionBuilder;
use Noem\State\Chains\Params\BuildParams;
use Noem\State\Feature\Transitions\AddTransition;
use Noem\State\Region;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @deprecated This monolithic test file is deprecated in favor of granular spec-based tests.
 *
 * Tests have been refactored into individual test classes under tests/PHPUnit/Unit/Core/RegionBuilder/
 * following the one-spec-one-test-class pattern as defined in specs/core/region-builder.yaml
 *
 * See CLAUDE.md for the spec-driven testing approach.
 *
 * This file is kept temporarily for backwards compatibility but will be removed in a future version.
 */
class RegionBuilderTest extends TestCase
{
    /**
     * Test middleware enhancement of builder
     *
     * Note: This functionality is now tested in:
     * - tests/PHPUnit/Unit/Core/RegionBuilder/EnhanceBuilderIntegrationTest.php
     */
    public function testPushMiddlewaresSingle(): void
    {
        $builder = new RegionBuilder();
        $builder->setStates('a', 'b')
            ->chainMail->use(
                function (EnhanceRegionBuilder $builderMiddleware) {
                    $builderMiddleware->link(function (BuildParams $params, callable $next): RegionBuilder {
                        $builder = $next($params);
                        // Add transition using BuildStep instead of non-existent pushTransition method
                        $builder->addBuildStep(new AddTransition('a', 'b', fn(object $t): bool => true));
                        return $builder;
                    });
                }
            );
        $region = $builder->build();
        $this->assertInstanceOf(Region::class, $region);
        $this->assertTrue(
            $region->isInState('a'),
            "Machine should be in state 'a' after building"
        );
        $region->trigger((object)['a' => 'b']);
        $this->assertTrue(
            $region->isInState('b'),
            "Machine should have switched states via a transition added through middleware"
        );
    }

    /**
     * Test multiple middleware registration
     *
     * Note: This functionality is now tested in:
     * - tests/PHPUnit/Unit/Core/RegionBuilder/EnhanceBuilderIntegrationTest.php
     */
    public function testPushMiddlewaresMultiple(): void
    {
        $builder = new RegionBuilder();
        $builder->chainMail->use(
            function (
                DispatchAction $regionMiddleware,
                EnhanceRegionBuilder $builderMiddleware
            ) {
                $regionMiddleware->link(function () {
                });
            }
        );
        $this->assertTrue(true); // Basic smoke test that middleware registration doesn't fail
    }
}
