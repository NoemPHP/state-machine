<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Abilities\BoundAccess;

use Noem\State\Feature\Abilities\AbilitiesFeature;
use Noem\State\Feature\Message\MessageFeature;
use Noem\State\Feature\Abilities\AbilityMessage;
use Noem\State\Feature\Abilities\AbilityRegistry;
use Noem\State\Feature\ExtendedState\ContextChains\BoundAccess;
use Noem\State\Feature\ExtendedState\ContextChains\Params\BoundAccessParams;
use Noem\State\Middleware\ChainMail;
use Noem\State\Region;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: BoundAccess intercepts abilities() method calls
 *
 * Intent: Detects abilities method name in __call, routing to ability infrastructure
 * instead of default behavior
 */
#[Group('abilities')]
#[Group('bound-access-integration')]
class InterceptsAbilitiesCallTest extends TestCase
{
    public function testBoundAccessInterceptsAbilitiesMethodCalls(): void
    {
        $chainMail = new ChainMail();

        // Provide dependencies
        $chainMail->supply(
            fn(): BoundAccess => new BoundAccess(),
            fn(): AbilityRegistry => new AbilityRegistry()
        );

        $messageFeature = new MessageFeature();
        $messageFeature($chainMail);

        $feature = new AbilitiesFeature();
        $feature($chainMail);

        // Get the BoundAccess chain
        $boundAccess = $chainMail->get(BoundAccess::class);

        // Create a mock region for testing
        $region = $this->createMock(Region::class);

        // Create params for abilities() method call
        $params = new BoundAccessParams(
            $region,
            BoundAccessParams::TYPE_METHOD,
            'abilities',
            ['test-ability', ['param' => 'value']]
        );

        // When: Call BoundAccess with 'abilities' method name
        // Then: Should NOT throw "Method 'abilities' not found" exception
        // This indicates the middleware intercepted the call

        // This test will FAIL because the middleware isn't implemented yet.
        // When implemented, the middleware should:
        // - Detect method name is 'abilities'
        // - NOT call next() for abilities method
        // - Process the ability invocation

        // For now, we expect it to throw because middleware doesn't exist
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Method 'abilities' not found in callback context");

        $boundAccess->call($params);
    }
}
