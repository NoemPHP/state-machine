<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\Feature\Loader\Container;
use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\Feature\Includes\IncludesFeature;
use Noem\State\Middleware\ChainMail;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: RegionLoader registers Container as a service
 */
#[Group('loader')]
#[Group('feature-registration')]
class ContainerRegistrationTest extends TestCase
{
    public function testRegistersContainerService(): void
    {
        $chainMail = new ChainMail();
        // RegionLoader depends on services normally registered by RegionBuilder
        $chainMail->supply(
            fn(): \Noem\State\Chains\ConnectedRegions => new \Noem\State\Chains\ConnectedRegions(),
            fn(): \Noem\State\Chains\EnhanceRegionBuilder => new \Noem\State\Chains\EnhanceRegionBuilder(),
            fn(): \Noem\State\Chains\ValidateCallback => new \Noem\State\Chains\ValidateCallback(),
            fn(): \Noem\State\Chains\PrepareInvokable => new \Noem\State\Chains\PrepareInvokable(),
            fn(): \Noem\State\Chains\InvokeCallback => new \Noem\State\Chains\InvokeCallback(),
            fn(): \Noem\State\Chains\ExtendedState => new \Noem\State\Chains\ExtendedState(),
            fn(): \Noem\State\Chains\Set => new \Noem\State\Chains\Set(),
            fn(): \Noem\State\Chains\Get => new \Noem\State\Chains\Get(),
            fn(\Noem\State\Chains\ConnectedRegions $c, \Noem\State\Events $e): \Noem\State\Chains\DispatchAction => new \Noem\State\Chains\DispatchAction($c, $e),
            fn(\Noem\State\Chains\ConnectedRegions $connections): \Noem\State\Chains\Path => new \Noem\State\Chains\Path($connections),
            fn(\Noem\State\Chains\ConnectedRegions $connectedRegions): \Noem\State\Chains\Meta => new \Noem\State\Chains\Meta($connectedRegions),
            \Noem\State\Events::conjure()
        );

        $includes = new IncludesFeature();
        $includes($chainMail);

        $loader = new RegionLoader();

        $loader($chainMail);

        $container = $chainMail->get(Container::class);

        $this->assertInstanceOf(Container::class, $container);
    }
}
