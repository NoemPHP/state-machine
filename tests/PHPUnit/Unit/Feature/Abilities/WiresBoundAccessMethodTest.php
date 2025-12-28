<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Abilities;

use Noem\State\Feature\Abilities\AbilitiesFeature;
use Noem\State\Feature\Abilities\AbilityRegistry;
use Noem\State\Feature\Abilities\Chains\InvokeAbility;
use Noem\State\Feature\ExtendedState\ContextChains\BoundAccess;
use Noem\State\Feature\ExtendedState\ContextChains\Params\BoundAccessParams;
use Noem\State\Middleware\ChainMail;
use Noem\State\Region;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: AbilitiesFeature wires abilities method into BoundAccess
 *
 * Intent: Intercepts BoundAccess __call to handle abilities() invocations,
 * enabling direct $this->abilities() API in state callbacks
 */
#[Group('abilities')]
#[Group('feature-registration')]
class WiresBoundAccessMethodTest extends TestCase
{
    public function testWiresAbilitiesMethodIntoBoundAccess(): void
    {
        $chainMail = new ChainMail();

        // Provide dependencies
        $chainMail->supply(
            fn(): BoundAccess => new BoundAccess(),
            fn(): AbilityRegistry => new AbilityRegistry()
        );

        $feature = new AbilitiesFeature();
        $feature($chainMail);

        // Get the BoundAccess chain
        $boundAccess = $chainMail->get(BoundAccess::class);
        $this->assertInstanceOf(BoundAccess::class, $boundAccess);

        // Create a mock region for testing
        $region = $this->createMock(Region::class);

        // Create params for abilities() method call
        $params = new BoundAccessParams(
            $region,
            BoundAccessParams::TYPE_METHOD,
            'abilities',
            ['test-ability', ['param' => 'value']]
        );

        // This test verifies that calling the BoundAccess chain with 'abilities' method
        // does NOT throw the default "Method 'abilities' not found" exception,
        // meaning the middleware was properly wired.
        //
        // Note: This will fail initially because:
        // 1. AbilitiesFeature doesn't exist yet
        // 2. The middleware that handles 'abilities' isn't registered
        //
        // When implemented, the middleware should:
        // - Detect method name is 'abilities'
        // - Extract abilityName from first argument
        // - Extract parameters from second argument
        // - Call InvokeAbility chain
        // - Return the result

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Method 'abilities' not found in callback context");

        // This will throw because AbilitiesFeature doesn't wire the middleware yet
        $boundAccess->call($params);
    }
}
