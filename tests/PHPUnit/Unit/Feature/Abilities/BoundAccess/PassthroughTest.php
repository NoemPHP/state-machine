<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Abilities\BoundAccess;

use Noem\State\Feature\Abilities\AbilitiesFeature;
use Noem\State\Feature\Message\MessageFeature;
use Noem\State\Feature\Abilities\AbilityRegistry;
use Noem\State\Feature\ExtendedState\ContextChains\BoundAccess;
use Noem\State\Feature\ExtendedState\ContextChains\Params\BoundAccessParams;
use Noem\State\Middleware\ChainMail;
use Noem\State\Region;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: BoundAccess passes non-abilities calls through unchanged
 *
 * Intent: Preserves normal BoundAccess behavior for other method names,
 * maintaining backward compatibility
 */
#[Group('abilities')]
#[Group('bound-access-integration')]
class PassthroughTest extends TestCase
{
    public function testPassesNonAbilitiesCallsThrough(): void
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

        // Install middleware that handles a different method
        $boundAccess->link(function (BoundAccessParams $params, callable $next) {
            if ($params->name === 'testMethod') {
                return 'handled by test middleware';
            }
            return $next($params);
        });

        // Create a mock region
        $region = $this->createMock(Region::class);

        // Test 1: Non-abilities method should pass through to other middleware
        $params = new BoundAccessParams(
            $region,
            BoundAccessParams::TYPE_METHOD,
            'testMethod',
            []
        );

        $result = $boundAccess->call($params);

        // Should be handled by our test middleware
        $this->assertEquals('handled by test middleware', $result);

        // Test 2: Unknown method should throw the default exception
        $params = new BoundAccessParams(
            $region,
            BoundAccessParams::TYPE_METHOD,
            'unknownMethod',
            []
        );

        // Should throw because no middleware handles it
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Method 'unknownMethod' not found in callback context");

        $boundAccess->call($params);
    }
}
