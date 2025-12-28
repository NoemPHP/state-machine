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
 * Acceptance Criterion: DispatchAction middleware executes AbilityDefinition handler with parameters
 *
 * Intent: Invokes ability business logic with invocation parameters, producing response data
 */
#[Group('abilities')]
#[Group('dispatch-middleware')]
class ExecutesHandlerTest extends TestCase
{
    public function testMiddlewareExecutesAbilityHandler(): void
    {
        $chainMail = new ChainMail();
        $registry = new AbilityRegistry();
        $handlerExecuted = false;

        // Register ability with handler
        $definition = new AbilityDefinition(
            name: 'execute-test',
            description: 'Test handler execution',
            parameterSchema: [],
            responseSchema: [],
            handler: function (mixed $params) use (&$handlerExecuted) {
                $handlerExecuted = true;
                return ['executed' => true];
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


        // Install AbilitiesFeature
        $feature = new AbilitiesFeature();
        $feature($chainMail);

        $dispatchChain = $chainMail->get(DispatchAction::class);

        // Create a mock region
        $region = $this->createMock(Region::class);
        $region->method('currentState')->willReturn('test-state');

        // Create AbilityMessage
        $abilityMessage = AbilityMessage::create(
            abilityName: 'execute-test',
            parameters: null
        );

        $action = new Action($region, $abilityMessage);

        // Call dispatch - should execute handler
        $dispatchChain->call($action);

        // Verify handler was executed
        $this->assertTrue(
            $handlerExecuted,
            'Middleware should execute ability handler'
        );
    }

    public function testMiddlewarePassesParametersToHandler(): void
    {
        $chainMail = new ChainMail();
        $registry = new AbilityRegistry();
        $receivedParams = null;

        // Register ability that captures parameters
        $definition = new AbilityDefinition(
            name: 'params-test',
            description: 'Test parameter passing',
            parameterSchema: [],
            responseSchema: [],
            handler: function (mixed $params) use (&$receivedParams) {
                $receivedParams = $params;
                return ['received' => $params];
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


        // Install AbilitiesFeature
        $feature = new AbilitiesFeature();
        $feature($chainMail);

        $dispatchChain = $chainMail->get(DispatchAction::class);

        // Create a mock region
        $region = $this->createMock(Region::class);
        $region->method('currentState')->willReturn('test-state');

        // Create message with specific parameters
        $testParams = ['key' => 'value', 'number' => 42];
        $abilityMessage = AbilityMessage::create(
            abilityName: 'params-test',
            parameters: $testParams
        );

        $action = new Action($region, $abilityMessage);

        // Call dispatch
        $dispatchChain->call($action);

        // Verify parameters were passed to handler
        $this->assertEquals(
            $testParams,
            $receivedParams,
            'Middleware should pass message.parameters to handler'
        );
    }

    public function testMiddlewareHandlesNullParameters(): void
    {
        $chainMail = new ChainMail();
        $registry = new AbilityRegistry();
        $receivedParams = 'not-null';

        // Register ability that handles null parameters
        $definition = new AbilityDefinition(
            name: 'null-params-test',
            description: 'Test null parameter handling',
            parameterSchema: [],
            responseSchema: [],
            handler: function (mixed $params) use (&$receivedParams) {
                $receivedParams = $params;
                return ['result' => 'ok'];
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


        // Install AbilitiesFeature
        $feature = new AbilitiesFeature();
        $feature($chainMail);

        $dispatchChain = $chainMail->get(DispatchAction::class);

        // Create a mock region
        $region = $this->createMock(Region::class);
        $region->method('currentState')->willReturn('test-state');

        // Create message with null parameters
        $abilityMessage = AbilityMessage::create(
            abilityName: 'null-params-test',
            parameters: null
        );

        $action = new Action($region, $abilityMessage);

        // Call dispatch
        $dispatchChain->call($action);

        // Verify null was passed to handler
        $this->assertNull(
            $receivedParams,
            'Middleware should pass null parameters to handler'
        );
    }

    public function testMiddlewareHandlesComplexParameters(): void
    {
        $chainMail = new ChainMail();
        $registry = new AbilityRegistry();
        $receivedParams = null;

        // Register ability
        $definition = new AbilityDefinition(
            name: 'complex-params-test',
            description: 'Test complex parameter structures',
            parameterSchema: [],
            responseSchema: [],
            handler: function (mixed $params) use (&$receivedParams) {
                $receivedParams = $params;
                return ['processed' => true];
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


        // Install AbilitiesFeature
        $feature = new AbilitiesFeature();
        $feature($chainMail);

        $dispatchChain = $chainMail->get(DispatchAction::class);

        // Create a mock region
        $region = $this->createMock(Region::class);
        $region->method('currentState')->willReturn('test-state');

        // Create message with complex nested parameters
        $complexParams = [
            'user' => [
                'name' => 'Alice',
                'roles' => ['admin', 'user'],
            ],
            'config' => [
                'timeout' => 30,
                'retries' => 3,
            ],
        ];

        $abilityMessage = AbilityMessage::create(
            abilityName: 'complex-params-test',
            parameters: $complexParams
        );

        $action = new Action($region, $abilityMessage);

        // Call dispatch
        $dispatchChain->call($action);

        // Verify complex parameters were passed correctly
        $this->assertEquals(
            $complexParams,
            $receivedParams,
            'Middleware should pass complex parameter structures to handler'
        );
    }
}
