<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Abilities\Dispatch;

use Noem\State\Chains\DispatchAction;
use Noem\State\Chains\Notification;
use Noem\State\Chains\Params\Action;
use Noem\State\Chains\Params\Notify;
use Noem\State\Feature\Abilities\AbilitiesFeature;
use Noem\State\Feature\Abilities\AbilityDefinition;
use Noem\State\Feature\Abilities\AbilityMessage;
use Noem\State\Feature\Abilities\AbilityRegistry;
use Noem\State\Middleware\ChainMail;
use Noem\State\Region;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: DispatchAction middleware emits response via Notification chain
 *
 * Intent: Publishes response message through notification infrastructure, triggering MessageFeature delivery
 */
#[Group('abilities')]
#[Group('dispatch-middleware')]
class EmitsResponseTest extends TestCase
{
    public function testMiddlewareEmitsResponseViaNotificationChain(): void
    {
        $chainMail = new ChainMail();
        $registry = new AbilityRegistry();
        $notificationCalled = false;
        $emittedEvent = null;

        // Mock Notification chain to verify emission
        $mockNotification = $this->createMock(Notification::class);
        $mockNotification->expects($this->once())
            ->method('call')
            ->willReturnCallback(function (Notify $notify) use (&$notificationCalled, &$emittedEvent) {
                $notificationCalled = true;
                $emittedEvent = $notify->event;
                return [];
            });

        // Register ability
        $definition = new AbilityDefinition(
            name: 'emit-test',
            description: 'Test response emission',
            parameterSchema: [],
            responseSchema: [],
            handler: fn($params) => ['emitted' => true]
        );
        $registry->register($definition);

        // Provide dependencies
        $chainMail->supply(
            fn(): DispatchAction => new DispatchAction(new \Noem\State\Chains\ConnectedRegions(), new \Noem\State\Events(new \Noem\State\Chains\ValidateCallback(), new \Noem\State\Chains\PrepareInvokable(), new \Noem\State\Chains\InvokeCallback(), new \Noem\State\Callbacks\CallbackRegistry())),
            fn(): Notification => $mockNotification,
            fn(): AbilityRegistry => $registry
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

        // Create request message
        $requestMessage = AbilityMessage::create(
            abilityName: 'emit-test',
            parameters: null
        );

        $action = new Action($region, $requestMessage);

        // Call dispatch
        $dispatchChain->call($action);

        // Verify Notification chain was called
        $this->assertTrue(
            $notificationCalled,
            'Middleware should emit response via Notification chain'
        );

        // Verify response is an AbilityMessage
        $this->assertInstanceOf(
            AbilityMessage::class,
            $emittedEvent,
            'Emitted event should be an AbilityMessage'
        );
    }

    public function testEmittedResponseIncludesRegion(): void
    {
        $chainMail = new ChainMail();
        $registry = new AbilityRegistry();
        $notifyParams = null;

        // Mock Notification chain to capture Notify params
        $mockNotification = $this->createMock(Notification::class);
        $mockNotification->expects($this->once())
            ->method('call')
            ->willReturnCallback(function (Notify $notify) use (&$notifyParams) {
                $notifyParams = $notify;
                return [];
            });

        // Register ability
        $definition = new AbilityDefinition(
            name: 'region-test',
            description: 'Test region in notification',
            parameterSchema: [],
            responseSchema: [],
            handler: fn($params) => ['region' => 'included']
        );
        $registry->register($definition);

        // Provide dependencies
        $chainMail->supply(
            fn(): DispatchAction => new DispatchAction(new \Noem\State\Chains\ConnectedRegions(), new \Noem\State\Events(new \Noem\State\Chains\ValidateCallback(), new \Noem\State\Chains\PrepareInvokable(), new \Noem\State\Chains\InvokeCallback(), new \Noem\State\Callbacks\CallbackRegistry())),
            fn(): Notification => $mockNotification,
            fn(): AbilityRegistry => $registry
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

        // Create request
        $requestMessage = AbilityMessage::create(
            abilityName: 'region-test',
            parameters: null
        );

        $action = new Action($region, $requestMessage);

        // Call dispatch
        $dispatchChain->call($action);

        // Verify Notify params includes region
        $this->assertInstanceOf(Notify::class, $notifyParams);
        $this->assertSame(
            $region,
            $notifyParams->region,
            'Notification should include the region'
        );
    }

    public function testEmitsResponseAfterHandlerExecution(): void
    {
        $chainMail = new ChainMail();
        $registry = new AbilityRegistry();
        $executionOrder = [];

        // Mock Notification chain to track order
        $mockNotification = $this->createMock(Notification::class);
        $mockNotification->expects($this->once())
            ->method('call')
            ->willReturnCallback(function (Notify $notify) use (&$executionOrder) {
                $executionOrder[] = 'notification';
                return [];
            });

        // Register ability that tracks execution
        $definition = new AbilityDefinition(
            name: 'order-test',
            description: 'Test execution order',
            parameterSchema: [],
            responseSchema: [],
            handler: function ($params) use (&$executionOrder) {
                $executionOrder[] = 'handler';
                return ['order' => 'tracked'];
            }
        );
        $registry->register($definition);

        // Provide dependencies
        $chainMail->supply(
            fn(): DispatchAction => new DispatchAction(new \Noem\State\Chains\ConnectedRegions(), new \Noem\State\Events(new \Noem\State\Chains\ValidateCallback(), new \Noem\State\Chains\PrepareInvokable(), new \Noem\State\Chains\InvokeCallback(), new \Noem\State\Callbacks\CallbackRegistry())),
            fn(): Notification => $mockNotification,
            fn(): AbilityRegistry => $registry
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

        // Create request
        $requestMessage = AbilityMessage::create(
            abilityName: 'order-test',
            parameters: null
        );

        $action = new Action($region, $requestMessage);

        // Call dispatch
        $dispatchChain->call($action);

        // Verify handler executes before notification
        $this->assertEquals(
            ['handler', 'notification'],
            $executionOrder,
            'Middleware should emit response after handler execution'
        );
    }

    public function testNotificationChainReceivesResponseMessage(): void
    {
        $chainMail = new ChainMail();
        $registry = new AbilityRegistry();
        $receivedMessage = null;

        // Mock Notification to capture message
        $mockNotification = $this->createMock(Notification::class);
        $mockNotification->expects($this->once())
            ->method('call')
            ->willReturnCallback(function (Notify $notify) use (&$receivedMessage) {
                $receivedMessage = $notify->event;
                return [];
            });

        // Register ability with specific result
        $handlerResult = ['specific' => 'result', 'value' => 123];
        $definition = new AbilityDefinition(
            name: 'message-test',
            description: 'Test message content',
            parameterSchema: [],
            responseSchema: [],
            handler: fn($params) => $handlerResult
        );
        $registry->register($definition);

        // Provide dependencies
        $chainMail->supply(
            fn(): DispatchAction => new DispatchAction(new \Noem\State\Chains\ConnectedRegions(), new \Noem\State\Events(new \Noem\State\Chains\ValidateCallback(), new \Noem\State\Chains\PrepareInvokable(), new \Noem\State\Chains\InvokeCallback(), new \Noem\State\Callbacks\CallbackRegistry())),
            fn(): Notification => $mockNotification,
            fn(): AbilityRegistry => $registry
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

        // Create request
        $requestMessage = AbilityMessage::create(
            abilityName: 'message-test',
            parameters: null
        );

        $action = new Action($region, $requestMessage);

        // Call dispatch
        $dispatchChain->call($action);

        // Verify notification received response message with handler result
        $this->assertInstanceOf(AbilityMessage::class, $receivedMessage);
        $this->assertEquals(
            $handlerResult,
            $receivedMessage->parameters,
            'Notification chain should receive response message with handler result'
        );
    }
}
