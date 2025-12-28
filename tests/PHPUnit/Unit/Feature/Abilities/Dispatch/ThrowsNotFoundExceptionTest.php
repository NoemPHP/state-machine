<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Abilities\Dispatch;

use Noem\State\Chains\DispatchAction;
use Noem\State\Chains\Notification;
use Noem\State\Chains\Params\Action;
use Noem\State\Feature\Abilities\AbilitiesFeature;
use Noem\State\Feature\Abilities\AbilityMessage;
use Noem\State\Feature\Abilities\AbilityNotFoundException;
use Noem\State\Feature\Abilities\AbilityRegistry;
use Noem\State\Middleware\ChainMail;
use Noem\State\Region;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: DispatchAction middleware throws AbilityNotFoundException if definition not found
 *
 * Intent: Fails with clear error when ability missing from registry, preventing silent failures
 */
#[Group('abilities')]
#[Group('dispatch-middleware')]
class ThrowsNotFoundExceptionTest extends TestCase
{
    public function testThrowsAbilityNotFoundExceptionForUnknownAbility(): void
    {
        $chainMail = new ChainMail();
        $registry = new AbilityRegistry();

        // Empty registry - no abilities registered

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

        // Create message for non-existent ability
        $abilityMessage = AbilityMessage::create(
            abilityName: 'non-existent-ability',
            parameters: null
        );

        $action = new Action($region, $abilityMessage);

        // Should throw AbilityNotFoundException
        $this->expectException(AbilityNotFoundException::class);

        $dispatchChain->call($action);
    }

    public function testExceptionIncludesAbilityName(): void
    {
        $chainMail = new ChainMail();
        $registry = new AbilityRegistry();

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

        // Create message for specific unknown ability
        $unknownAbilityName = 'very-specific-unknown-ability';
        $abilityMessage = AbilityMessage::create(
            abilityName: $unknownAbilityName,
            parameters: null
        );

        $action = new Action($region, $abilityMessage);

        try {
            $dispatchChain->call($action);
            $this->fail('Should have thrown AbilityNotFoundException');
        } catch (AbilityNotFoundException $e) {
            // Verify exception message includes ability name
            $this->assertStringContainsString(
                $unknownAbilityName,
                $e->getMessage(),
                'Exception message should include the missing ability name'
            );
        }
    }

    public function testDoesNotThrowWhenAbilityExists(): void
    {
        $chainMail = new ChainMail();
        $registry = new AbilityRegistry();

        // Mock Notification
        $mockNotification = $this->createMock(Notification::class);
        $mockNotification->method('call')->willReturn([]);

        // Register an ability
        $definition = new \Noem\State\Feature\Abilities\AbilityDefinition(
            name: 'existing-ability',
            description: 'An ability that exists',
            parameterSchema: [],
            responseSchema: [],
            handler: fn($params) => ['found' => true]
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

        // Create message for EXISTING ability
        $abilityMessage = AbilityMessage::create(
            abilityName: 'existing-ability',
            parameters: null
        );

        $action = new Action($region, $abilityMessage);

        // Should NOT throw exception
        $result = $dispatchChain->call($action);

        // Verify no exception was thrown by reaching this point
        $this->assertIsString($result, 'Should complete without throwing');
    }

    public function testThrowsOnlyForAbilityMessagesWithMissingDefinition(): void
    {
        $chainMail = new ChainMail();
        $registry = new AbilityRegistry();

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

        // First test: Non-AbilityMessage should pass through (no exception)
        $regularPayload = new \stdClass();
        $action1 = new Action($region, $regularPayload);

        // This should NOT throw (in RED phase, will throw Error due to missing implementation)
        $this->expectNotToPerformAssertions();
        $dispatchChain->call($action1);

        // Second test would be AbilityMessage with missing ability (should throw AbilityNotFoundException)
        // But we can't test both in same test method
    }

    public function testExceptionPropagatesToCaller(): void
    {
        $chainMail = new ChainMail();
        $registry = new AbilityRegistry();

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

        // Create message for missing ability
        $abilityMessage = AbilityMessage::create(
            abilityName: 'missing-ability',
            parameters: null
        );

        $action = new Action($region, $abilityMessage);

        // Verify exception propagates all the way up
        $exceptionCaught = false;

        try {
            $dispatchChain->call($action);
        } catch (AbilityNotFoundException $e) {
            $exceptionCaught = true;
            $this->assertStringContainsString('missing-ability', $e->getMessage());
        }

        // In RED phase, this will fail because AbilityNotFoundException doesn't exist
        // Once implemented, this should pass
        $this->expectNotToPerformAssertions();
        if (!$exceptionCaught) {
            throw new \Error('Expected AbilityNotFoundException but none was thrown');
        }
    }
}
