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
 * Acceptance Criterion: DispatchAction middleware creates response AbilityMessage with handler result
 *
 * Intent: Constructs correlated response message containing handler output, enabling correlation-based delivery
 */
#[Group('abilities')]
#[Group('dispatch-middleware')]
class CreatesResponseMessageTest extends TestCase
{
    public function testMiddlewareCreatesResponseFromHandlerResult(): void
    {
        $chainMail = new ChainMail();
        $registry = new AbilityRegistry();
        $emittedResponse = null;

        // Mock Notification chain to capture emitted response
        $mockNotification = $this->createMock(Notification::class);
        $mockNotification->expects($this->once())
            ->method('call')
            ->willReturnCallback(function (Notify $notify) use (&$emittedResponse) {
                $emittedResponse = $notify->event;
                return [];
            });

        // Register ability with handler that returns data
        $handlerResult = ['result' => 'success', 'value' => 42];
        $definition = new AbilityDefinition(
            name: 'response-test',
            description: 'Test response creation',
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

        // Create AbilityMessage
        $requestMessage = AbilityMessage::create(
            abilityName: 'response-test',
            parameters: null
        );

        $action = new Action($region, $requestMessage);

        // Call dispatch - should create and emit response
        $dispatchChain->call($action);

        // Verify response message was created
        $this->assertInstanceOf(
            AbilityMessage::class,
            $emittedResponse,
            'Middleware should create AbilityMessage response'
        );
    }

    public function testResponseMessageContainsHandlerResult(): void
    {
        $chainMail = new ChainMail();
        $registry = new AbilityRegistry();
        $emittedResponse = null;

        // Mock Notification chain to capture response
        $mockNotification = $this->createMock(Notification::class);
        $mockNotification->expects($this->once())
            ->method('call')
            ->willReturnCallback(function (Notify $notify) use (&$emittedResponse) {
                $emittedResponse = $notify->event;
                return [];
            });

        // Handler returns specific data
        $expectedResult = [
            'status' => 'completed',
            'data' => ['foo' => 'bar'],
        ];

        $definition = new AbilityDefinition(
            name: 'result-test',
            description: 'Test result in response',
            parameterSchema: [],
            responseSchema: [],
            handler: fn($params) => $expectedResult
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
            abilityName: 'result-test',
            parameters: null
        );

        $action = new Action($region, $requestMessage);

        // Call dispatch
        $dispatchChain->call($action);

        // Verify response contains handler result as parameters
        $this->assertInstanceOf(AbilityMessage::class, $emittedResponse);
        $this->assertEquals(
            $expectedResult,
            $emittedResponse->parameters,
            'Response message should contain handler result as parameters'
        );
    }

    public function testUsesCreateResponseMethod(): void
    {
        $chainMail = new ChainMail();
        $registry = new AbilityRegistry();
        $emittedResponse = null;

        // Mock Notification chain
        $mockNotification = $this->createMock(Notification::class);
        $mockNotification->expects($this->once())
            ->method('call')
            ->willReturnCallback(function (Notify $notify) use (&$emittedResponse) {
                $emittedResponse = $notify->event;
                return [];
            });

        // Register ability
        $definition = new AbilityDefinition(
            name: 'create-method-test',
            description: 'Test createResponse method usage',
            parameterSchema: [],
            responseSchema: [],
            handler: fn($params) => ['used' => 'createResponse']
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
        $requestMessage = AbilityMessage::create('create-method-test', null);

        $action = new Action($region, $requestMessage);

        // Call dispatch - should use message.createResponse()
        $dispatchChain->call($action);

        // Verify response was created with correlation
        $this->assertInstanceOf(AbilityMessage::class, $emittedResponse);
        $this->assertNotNull($emittedResponse->correlationId);
        $this->assertEquals(
            $requestMessage->correlationId,
            $emittedResponse->correlationId,
            'Response should have same correlation ID as request'
        );
    }
}
