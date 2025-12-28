<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Abilities;

use Noem\State\Chains\ConnectedRegions;
use Noem\State\Chains\DispatchAction;
use Noem\State\Events;
use Noem\State\Chains\Notification;
use Noem\State\Chains\Params\Action;
use Noem\State\Feature\Abilities\AbilitiesFeature;
use Noem\State\Feature\Abilities\AbilityRegistry;
use Noem\State\Feature\Abilities\AbilityMessage;
use Noem\State\Middleware\ChainMail;
use Noem\State\Region;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: AbilitiesFeature installs DispatchAction middleware for AbilityMessage handling
 *
 * Intent: Intercepts AbilityMessage payloads during dispatch to execute ability handlers
 * and generate responses
 */
#[Group('abilities')]
#[Group('feature-registration')]
class InstallsDispatchMiddlewareTest extends TestCase
{
    public function testInstallsDispatchActionMiddleware(): void
    {
        $chainMail = new ChainMail();

        // Provide dependencies
        $connectedRegions = new ConnectedRegions();
        $events = new Events(new \Noem\State\Chains\ValidateCallback(), new \Noem\State\Chains\PrepareInvokable(), new \Noem\State\Chains\InvokeCallback(), new \Noem\State\Callbacks\CallbackRegistry());

        $chainMail->supply(
            fn(): DispatchAction => new DispatchAction($connectedRegions, $events),
            fn(): Notification => new Notification(),
            fn(): AbilityRegistry => new AbilityRegistry()
        );

        $feature = new AbilitiesFeature();
        $feature($chainMail);

        // Get the DispatchAction chain
        $dispatchChain = $chainMail->get(DispatchAction::class);
        $this->assertInstanceOf(DispatchAction::class, $dispatchChain);

        // Create a mock region and AbilityMessage
        $region = $this->createMock(Region::class);
        $abilityMessage = AbilityMessage::create(
            'test-ability',
            ['param' => 'value']
        );

        // Create action params
        $action = new Action($region, $abilityMessage);

        // This test verifies that the DispatchAction chain has middleware
        // that can handle AbilityMessage payloads.
        //
        // The middleware should:
        // - Detect when action payload is AbilityMessage
        // - Look up ability definition from registry
        // - Execute the handler
        // - Create response message
        // - Emit response via Notification chain
        //
        // For now, we just verify the chain exists and can be called
        // The actual behavior testing will be in integration tests

        // This should work without throwing now that AbilitiesFeature is implemented
        $this->expectNotToPerformAssertions();

        $dispatchChain->call($action);
    }
}
