<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Abilities\Dispatch;

use Noem\State\Chains\DispatchAction;
use Noem\State\Chains\Notification;
use Noem\State\Chains\Params\Action;
use Noem\State\Feature\Abilities\AbilitiesFeature;
use Noem\State\Feature\Abilities\AbilityDefinition;
use Noem\State\Feature\Abilities\AbilityMessage;
use Noem\State\Feature\Abilities\AbilityRegistry;
use Noem\State\Middleware\ChainMail;
use Noem\State\Region;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: DispatchAction middleware intercepts AbilityMessage payloads
 *
 * Intent: Detects when dispatched action contains AbilityMessage, routing to ability handler execution
 */
#[Group('abilities')]
#[Group('dispatch-middleware')]
class InterceptsAbilityMessageTest extends TestCase
{
    public function testMiddlewareInterceptsAbilityMessagePayload(): void
    {
        $chainMail = new ChainMail();
        $registry = new AbilityRegistry();
        $handlerExecuted = false;

        // Register an ability with a handler
        $definition = new AbilityDefinition(
            name: 'test-ability',
            description: 'Test ability',
            parameterSchema: [],
            responseSchema: [],
            handler: function (mixed $params) use (&$handlerExecuted) {
                $handlerExecuted = true;
                return ['result' => 'success'];
            }
        );
        $registry->register($definition);

        // Provide dependencies
        $chainMail->supply(
            fn(): DispatchAction => new DispatchAction(new \Noem\State\Chains\ConnectedRegions(), new \Noem\State\Events(new \Noem\State\Chains\ValidateCallback(), new \Noem\State\Chains\PrepareInvokable(), new \Noem\State\Chains\InvokeCallback(), new \Noem\State\Callbacks\CallbackRegistry())),
            fn(): Notification => new Notification(),
            fn(): AbilityRegistry => $registry
        );

        // Load MessageFeature first (required dependency)
        $messageFeature = new \Noem\State\Feature\Message\MessageFeature();
        $messageFeature($chainMail);


        // Install AbilitiesFeature to wire up middleware
        $feature = new AbilitiesFeature();
        $feature($chainMail);

        // Get the DispatchAction chain (now with middleware)
        $dispatchChain = $chainMail->get(DispatchAction::class);

        // Create a mock region
        $region = $this->createMock(Region::class);
        $region->method('currentState')->willReturn('test-state');

        // Create an AbilityMessage payload
        $abilityMessage = AbilityMessage::create(
            abilityName: 'test-ability',
            parameters: ['param' => 'value'],
            definition: $definition
        );

        // Create action params
        $action = new Action($region, $abilityMessage);

        // Call the dispatch chain
        $dispatchChain->call($action);

        // Verify the middleware intercepted and executed the handler
        $this->assertTrue(
            $handlerExecuted,
            'DispatchAction middleware should intercept AbilityMessage and execute handler'
        );
    }

    public function testMiddlewareOnlyProcessesAbilityMessageType(): void
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
        $region->method('currentState')->willReturn('test-state');

        // Create an AbilityMessage
        $abilityMessage = AbilityMessage::create(
            abilityName: 'test-ability',
            parameters: null
        );

        $action = new Action($region, $abilityMessage);

        // The middleware should detect this is an AbilityMessage
        // This will fail in RED phase because:
        // 1. AbilityMessage class doesn't exist yet
        // 2. The middleware isn't installed
        // 3. The instanceof check in middleware doesn't exist
        $this->expectNotToPerformAssertions();

        $dispatchChain->call($action);
    }
}
