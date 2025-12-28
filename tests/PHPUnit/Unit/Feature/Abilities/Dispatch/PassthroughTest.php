<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Abilities\Dispatch;

use Noem\State\Chains\DispatchAction;
use Noem\State\Chains\Notification;
use Noem\State\Chains\Params\Action;
use Noem\State\Feature\Abilities\AbilitiesFeature;
use Noem\State\Feature\Abilities\AbilityRegistry;
use Noem\State\Middleware\ChainMail;
use Noem\State\Region;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Acceptance Criterion: DispatchAction middleware passes non-AbilityMessage payloads through
 *
 * Intent: Preserves normal dispatch behavior for other message types, maintaining separation of concerns
 */
#[Group('abilities')]
#[Group('dispatch-middleware')]
class PassthroughTest extends TestCase
{
    public function testMiddlewareIgnoresNonAbilityMessagePayloads(): void
    {
        $chainMail = new ChainMail();
        $nextCalled = false;

        // Mock DispatchAction to verify middleware calls next()
        $mockDispatch = $this->createMock(DispatchAction::class);
        $mockDispatch->expects($this->once())
            ->method('call')
            ->willReturnCallback(function () use (&$nextCalled) {
                $nextCalled = true;
                return 'test-state';
            });

        // Provide dependencies
        $chainMail->supply(
            fn(): DispatchAction => $mockDispatch,
            fn(): Notification => new Notification(),
            fn(): AbilityRegistry => new AbilityRegistry()
        );

        // Load MessageFeature first (required dependency)
        $messageFeature = new \Noem\State\Feature\Message\MessageFeature();
        $messageFeature($chainMail);

        // Install AbilitiesFeature
        $feature = new AbilitiesFeature();
        $feature($chainMail);

        $dispatchChain = $chainMail->get(DispatchAction::class);

        // Create a mock region
        $region = $this->createMock(Region::class);
        $region->method('currentState')->willReturn('test-state');

        // Create a NON-AbilityMessage payload (regular stdClass)
        $regularPayload = new stdClass();
        $regularPayload->data = 'regular event';

        $action = new Action($region, $regularPayload);

        // Call the dispatch chain
        $result = $dispatchChain->call($action);

        // Verify middleware passed through to next()
        $this->assertTrue(
            $nextCalled,
            'Middleware should call next() for non-AbilityMessage payloads'
        );

        // Verify result is preserved
        $this->assertEquals('test-state', $result);
    }

    public function testMiddlewareDoesNotInterceptStdClassPayloads(): void
    {
        $chainMail = new ChainMail();

        // Provide dependencies
        $chainMail->supply(
            fn(): DispatchAction => new DispatchAction(new \Noem\State\Chains\ConnectedRegions(), new \Noem\State\Events(new \Noem\State\Chains\ValidateCallback(), new \Noem\State\Chains\PrepareInvokable(), new \Noem\State\Chains\InvokeCallback(), new \Noem\State\Callbacks\CallbackRegistry())),
            fn(): Notification => new Notification(),
            fn(): AbilityRegistry => new AbilityRegistry()
        );

        // Load MessageFeature first (required dependency)
        $messageFeature = new \Noem\State\Feature\Message\MessageFeature();
        $messageFeature($chainMail);

        // Install AbilitiesFeature
        $feature = new AbilitiesFeature();
        $feature($chainMail);

        $dispatchChain = $chainMail->get(DispatchAction::class);

        // Create a mock region
        $region = $this->createMock(Region::class);
        $region->method('currentState')->willReturn('idle');

        // Create a regular stdClass payload
        $payload = new stdClass();

        $action = new Action($region, $payload);

        // This should NOT trigger ability handling
        // The middleware should check instanceof AbilityMessage and skip processing
        // We expect the chain to complete normally (will fail in RED phase)
        $this->expectNotToPerformAssertions();

        $dispatchChain->call($action);
    }

    public function testMiddlewarePreservesChainBehaviorForOtherFeatures(): void
    {
        $chainMail = new ChainMail();

        // Provide dependencies
        $chainMail->supply(
            fn(): DispatchAction => new DispatchAction(new \Noem\State\Chains\ConnectedRegions(), new \Noem\State\Events(new \Noem\State\Chains\ValidateCallback(), new \Noem\State\Chains\PrepareInvokable(), new \Noem\State\Chains\InvokeCallback(), new \Noem\State\Callbacks\CallbackRegistry())),
            fn(): Notification => new Notification(),
            fn(): AbilityRegistry => new AbilityRegistry()
        );

        // Load MessageFeature first (required dependency)
        $messageFeature = new \Noem\State\Feature\Message\MessageFeature();
        $messageFeature($chainMail);

        // Install AbilitiesFeature
        $feature = new AbilitiesFeature();
        $feature($chainMail);

        $dispatchChain = $chainMail->get(DispatchAction::class);

        // Create a mock region
        $region = $this->createMock(Region::class);
        $region->method('currentState')->willReturn('active');

        // Various non-AbilityMessage payloads
        $payloads = [
            new stdClass(),
            (object)['type' => 'custom'],
            (object)['event' => 'something'],
        ];

        foreach ($payloads as $payload) {
            $action = new Action($region, $payload);

            // Each should pass through without ability processing
            // Will fail in RED phase
            $this->expectNotToPerformAssertions();

            $dispatchChain->call($action);
        }
    }
}
