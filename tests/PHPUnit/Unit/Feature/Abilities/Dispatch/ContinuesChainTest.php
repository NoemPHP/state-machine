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
 * Acceptance Criterion: DispatchAction middleware continues dispatch chain after handling
 *
 * Intent: Calls next middleware to preserve dispatch pipeline, supporting other dispatch interceptors
 */
#[Group('abilities')]
#[Group('dispatch-middleware')]
class ContinuesChainTest extends TestCase
{
    public function testMiddlewareCallsNextInChain(): void
    {
        $chainMail = new ChainMail();
        $registry = new AbilityRegistry();
        $nextCalled = false;

        // Mock underlying DispatchAction to verify next() is called
        $mockDispatch = $this->createMock(DispatchAction::class);
        $mockDispatch->expects($this->once())
            ->method('call')
            ->willReturnCallback(function (Action $action) use (&$nextCalled) {
                $nextCalled = true;
                return $action->currentState;
            });

        // Mock Notification
        $mockNotification = $this->createMock(Notification::class);
        $mockNotification->method('call')->willReturn([]);

        // Register ability
        $definition = new AbilityDefinition(
            name: 'next-test',
            description: 'Test next() call',
            parameterSchema: [],
            responseSchema: [],
            handler: fn($params) => ['continues' => true]
        );
        $registry->register($definition);

        // Provide dependencies
        $chainMail->supply(
            fn(): DispatchAction => $mockDispatch,
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
        $region->method('currentState')->willReturn('active');

        // Create AbilityMessage
        $abilityMessage = AbilityMessage::create(
            abilityName: 'next-test',
            parameters: null
        );

        $action = new Action($region, $abilityMessage);

        // Call dispatch chain
        $dispatchChain->call($action);

        // Verify next() was called in the middleware chain
        $this->assertTrue(
            $nextCalled,
            'Middleware should call next() to continue dispatch chain'
        );
    }

    public function testMiddlewareReturnsResultFromNext(): void
    {
        $chainMail = new ChainMail();
        $registry = new AbilityRegistry();

        // Mock DispatchAction to return specific state
        $mockDispatch = $this->createMock(DispatchAction::class);
        $mockDispatch->expects($this->once())
            ->method('call')
            ->willReturn('returned-state');

        // Mock Notification
        $mockNotification = $this->createMock(Notification::class);
        $mockNotification->method('call')->willReturn([]);

        // Register ability
        $definition = new AbilityDefinition(
            name: 'return-test',
            description: 'Test return value',
            parameterSchema: [],
            responseSchema: [],
            handler: fn($params) => ['returns' => 'value']
        );
        $registry->register($definition);

        // Provide dependencies
        $chainMail->supply(
            fn(): DispatchAction => $mockDispatch,
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
        $region->method('currentState')->willReturn('active');

        // Create AbilityMessage
        $abilityMessage = AbilityMessage::create(
            abilityName: 'return-test',
            parameters: null
        );

        $action = new Action($region, $abilityMessage);

        // Call dispatch chain
        $result = $dispatchChain->call($action);

        // Verify middleware returns the result from next()
        $this->assertEquals(
            'returned-state',
            $result,
            'Middleware should return result from next() in chain'
        );
    }

    public function testMiddlewarePreservesDispatchPipeline(): void
    {
        $chainMail = new ChainMail();
        $registry = new AbilityRegistry();

        // Mock DispatchAction that processes the action
        $mockDispatch = $this->createMock(DispatchAction::class);
        $mockDispatch->expects($this->once())
            ->method('call')
            ->willReturnCallback(function (Action $action) {
                // Simulate normal dispatch behavior
                return $action->currentState;
            });

        // Mock Notification
        $mockNotification = $this->createMock(Notification::class);
        $mockNotification->method('call')->willReturn([]);

        // Register ability
        $definition = new AbilityDefinition(
            name: 'pipeline-test',
            description: 'Test pipeline preservation',
            parameterSchema: [],
            responseSchema: [],
            handler: fn($params) => ['pipeline' => 'preserved']
        );
        $registry->register($definition);

        // Provide dependencies
        $chainMail->supply(
            fn(): DispatchAction => $mockDispatch,
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
        $region->method('currentState')->willReturn('current-state');

        // Create AbilityMessage
        $abilityMessage = AbilityMessage::create(
            abilityName: 'pipeline-test',
            parameters: null
        );

        $action = new Action($region, $abilityMessage);

        // Call dispatch - should execute ability handler AND continue pipeline
        $result = $dispatchChain->call($action);

        // Verify state is preserved from pipeline
        $this->assertEquals(
            'current-state',
            $result,
            'Middleware should preserve dispatch pipeline result'
        );
    }

    public function testMiddlewareDoesNotShortCircuitChain(): void
    {
        $chainMail = new ChainMail();
        $registry = new AbilityRegistry();
        $pipelineCompleted = false;

        // Mock DispatchAction to track completion
        $mockDispatch = $this->createMock(DispatchAction::class);
        $mockDispatch->expects($this->once())
            ->method('call')
            ->willReturnCallback(function (Action $action) use (&$pipelineCompleted) {
                $pipelineCompleted = true;
                return $action->currentState;
            });

        // Mock Notification
        $mockNotification = $this->createMock(Notification::class);
        $mockNotification->method('call')->willReturn([]);

        // Register ability
        $definition = new AbilityDefinition(
            name: 'no-short-circuit-test',
            description: 'Test no short-circuit',
            parameterSchema: [],
            responseSchema: [],
            handler: fn($params) => ['no' => 'short-circuit']
        );
        $registry->register($definition);

        // Provide dependencies
        $chainMail->supply(
            fn(): DispatchAction => $mockDispatch,
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
        $abilityMessage = AbilityMessage::create(
            abilityName: 'no-short-circuit-test',
            parameters: null
        );

        $action = new Action($region, $abilityMessage);

        // Call dispatch
        $dispatchChain->call($action);

        // Verify pipeline completed (middleware didn't short-circuit)
        $this->assertTrue(
            $pipelineCompleted,
            'Middleware should not short-circuit the dispatch chain'
        );
    }
}
