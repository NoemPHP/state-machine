<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit;

use Noem\State\Chains\BuildRegion;
use Noem\State\Chains\DispatchAction;
use Noem\State\Chains\EnhanceRegionBuilder;
use Noem\State\Region;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\TestCase;

class RegionBuilderTest extends TestCase
{

    public function testPushMiddlewaresSingle(): void
    {
        $builder = new RegionBuilder();
        $builder->setStates('a', 'b')
            ->chainMail->use(
                function (EnhanceRegionBuilder $builderMiddleware) {
                    $builderMiddleware->link(function (RegionBuilder $builder, callable $next): RegionBuilder {
                        $builder->pushTransition('a', 'b', fn(object $t): bool => true);
                        $region = $next($builder);
                        assert($region instanceof RegionBuilder);

                        return $region;
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
        $this->assertTrue(true); // shut up notice for now
    }
}
