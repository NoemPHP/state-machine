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
 * Acceptance Criterion: DispatchAction middleware uses createResponse() with inherited correlation ID
 *
 * Intent: Ensures response has same correlation ID as request, enabling MessageFeature to deliver to then() handlers
 */
#[Group('abilities')]
#[Group('dispatch-middleware')]
class InheritsCorrelationIdTest extends TestCase
{
    public function testResponseInheritsCorrelationIdFromRequest(): void
    {
        $chainMail = new ChainMail();
        $registry = new AbilityRegistry();
        $emittedResponse = null;

        // Mock Notification to capture response
        $mockNotification = $this->createMock(Notification::class);
        $mockNotification->expects($this->once())
            ->method('call')
            ->willReturnCallback(function (Notify $notify) use (&$emittedResponse) {
                $emittedResponse = $notify->event;
                return [];
            });

        // Register ability
        $definition = new AbilityDefinition(
            name: 'correlation-test',
            description: 'Test correlation ID inheritance',
            parameterSchema: [],
            responseSchema: [],
            handler: fn($params) => ['correlated' => true]
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

        // Create request with specific correlation ID
        $requestMessage = AbilityMessage::create(
            abilityName: 'correlation-test',
            parameters: null,
            definition: null,
            correlationId: 'test-correlation-id-12345'
        );

        $action = new Action($region, $requestMessage);

        // Call dispatch
        $dispatchChain->call($action);

        // Verify response has same correlation ID as request
        $this->assertInstanceOf(AbilityMessage::class, $emittedResponse);
        $this->assertEquals(
            $requestMessage->correlationId(),
            $emittedResponse->correlationId(),
            'Response message should inherit correlation ID from request'
        );
    }

    public function testResponseRepliesToRequest(): void
    {
        $chainMail = new ChainMail();
        $registry = new AbilityRegistry();
        $emittedResponse = null;

        // Mock Notification
        $mockNotification = $this->createMock(Notification::class);
        $mockNotification->expects($this->once())
            ->method('call')
            ->willReturnCallback(function (Notify $notify) use (&$emittedResponse) {
                $emittedResponse = $notify->event;
                return [];
            });

        // Register ability
        $definition = new AbilityDefinition(
            name: 'replies-to-test',
            description: 'Test repliesTo relationship',
            parameterSchema: [],
            responseSchema: [],
            handler: fn($params) => ['reply' => true]
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
            abilityName: 'replies-to-test',
            parameters: null
        );

        $action = new Action($region, $requestMessage);

        // Call dispatch
        $dispatchChain->call($action);

        // Verify response.repliesTo(request) returns true
        $this->assertInstanceOf(AbilityMessage::class, $emittedResponse);
        $this->assertTrue(
            $emittedResponse->repliesTo($requestMessage),
            'Response should reply to request message'
        );
    }

    public function testUsesMessageCreateResponseMethod(): void
    {
        $chainMail = new ChainMail();
        $registry = new AbilityRegistry();
        $emittedResponse = null;

        // Mock Notification
        $mockNotification = $this->createMock(Notification::class);
        $mockNotification->expects($this->once())
            ->method('call')
            ->willReturnCallback(function (Notify $notify) use (&$emittedResponse) {
                $emittedResponse = $notify->event;
                return [];
            });

        // Register ability
        $definition = new AbilityDefinition(
            name: 'create-response-test',
            description: 'Test createResponse usage',
            parameterSchema: [],
            responseSchema: [],
            handler: fn($params) => ['method' => 'used']
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

        // Create request with explicit correlation ID
        $explicitCorrelationId = 'explicit-correlation-uuid';
        $requestMessage = AbilityMessage::create(
            abilityName: 'create-response-test',
            parameters: null,
            definition: null,
            correlationId: $explicitCorrelationId
        );

        $action = new Action($region, $requestMessage);

        // Call dispatch - middleware should use message.createResponse()
        $dispatchChain->call($action);

        // Verify createResponse() preserved the correlation ID
        $this->assertInstanceOf(AbilityMessage::class, $emittedResponse);
        $this->assertEquals(
            $explicitCorrelationId,
            $emittedResponse->correlationId(),
            'createResponse() should preserve correlation ID from request'
        );
    }

    public function testMultipleRequestsPreserveTheirCorrelationIds(): void
    {
        $chainMail = new ChainMail();
        $registry = new AbilityRegistry();
        $emittedResponses = [];

        // Mock Notification to collect all responses
        $mockNotification = $this->createMock(Notification::class);
        $mockNotification->expects($this->exactly(2))
            ->method('call')
            ->willReturnCallback(function (Notify $notify) use (&$emittedResponses) {
                $emittedResponses[] = $notify->event;
                return [];
            });

        // Register ability
        $definition = new AbilityDefinition(
            name: 'multi-correlation-test',
            description: 'Test multiple correlation IDs',
            parameterSchema: [],
            responseSchema: [],
            handler: fn($params) => ['handled' => true]
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

        // Create two requests with different correlation IDs
        $request1 = AbilityMessage::create(
            abilityName: 'multi-correlation-test',
            parameters: null,
            definition: null,
            correlationId: 'correlation-1'
        );

        $request2 = AbilityMessage::create(
            abilityName: 'multi-correlation-test',
            parameters: null,
            definition: null,
            correlationId: 'correlation-2'
        );

        // Dispatch both
        $dispatchChain->call(new Action($region, $request1));
        $dispatchChain->call(new Action($region, $request2));

        // Verify each response has its own correlation ID
        $this->assertCount(2, $emittedResponses);
        $this->assertEquals('correlation-1', $emittedResponses[0]->correlationId());
        $this->assertEquals('correlation-2', $emittedResponses[1]->correlationId());
    }
}
