<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\Feature\Loader\LoaderChains\TransformArray;
use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\Feature\Includes\IncludesFeature;
use Noem\State\Middleware\ChainMail;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: RegionLoader registers TransformArray chain as a service
 */
#[Group('loader')]
#[Group('feature-registration')]
class TransformArrayChainRegistrationTest extends TestCase
{
    public function testRegistersTransformArrayChain(): void
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
            fn(): \Noem\State\Callbacks\CallbackRegistry => new \Noem\State\Callbacks\CallbackRegistry(),
            \Noem\State\Events::conjure()
        );

        $includes = new IncludesFeature();
        $includes($chainMail);

        $loader = new RegionLoader();

        $loader($chainMail);

        $transformArray = $chainMail->get(TransformArray::class);

        $this->assertInstanceOf(TransformArray::class, $transformArray);
    }
}
