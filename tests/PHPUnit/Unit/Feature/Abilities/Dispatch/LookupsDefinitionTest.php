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
 * Acceptance Criterion: DispatchAction middleware looks up AbilityDefinition from registry
 *
 * Intent: Retrieves ability definition for handler execution, supporting schema and handler access
 */
#[Group('abilities')]
#[Group('dispatch-middleware')]
class LookupsDefinitionTest extends TestCase
{
    public function testMiddlewareRetrievesDefinitionFromRegistry(): void
    {
        $chainMail = new ChainMail();
        $registry = new AbilityRegistry();
        $definitionUsed = null;

        // Register an ability
        $definition = new AbilityDefinition(
            name: 'lookup-test',
            description: 'Test definition lookup',
            parameterSchema: [],
            responseSchema: [],
            handler: function (mixed $params) use (&$definitionUsed, &$definition) {
                $definitionUsed = $definition;
                return ['looked' => 'up'];
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

        // Create AbilityMessage WITHOUT definition (force registry lookup)
        $abilityMessage = AbilityMessage::create(
            abilityName: 'lookup-test',
            parameters: null
        );

        $action = new Action($region, $abilityMessage);

        // Call the dispatch chain - middleware should look up definition from registry
        $dispatchChain->call($action);

        // Verify the correct definition was used
        $this->assertSame(
            $definition,
            $definitionUsed,
            'Middleware should retrieve definition from registry by abilityName'
        );
    }

    public function testMiddlewareUsesRegistryGetMethod(): void
    {
        $chainMail = new ChainMail();
        $getCallCount = 0;

        // Create a spy registry to track get() calls
        $registry = new class ($getCallCount) extends AbilityRegistry {
            private int $callCount = 0;

            public function __construct(private int &$getCallCountRef)
            {
            }

            public function get(string $name): ?AbilityDefinition
            {
                $this->getCallCountRef++;
                return parent::get($name);
            }
        };

        // Register an ability
        $definition = new AbilityDefinition(
            name: 'tracked-ability',
            description: 'Track registry access',
            parameterSchema: [],
            responseSchema: [],
            handler: fn($p) => ['tracked' => true]
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
            abilityName: 'tracked-ability',
            parameters: null
        );

        $action = new Action($region, $abilityMessage);

        // Call dispatch - should trigger registry.get()
        $dispatchChain->call($action);

        // Verify registry.get() was called
        $this->assertGreaterThan(
            0,
            $getCallCount,
            'Middleware should call registry.get() to lookup definition'
        );
    }

    public function testMiddlewareLookupsDefinitionByAbilityName(): void
    {
        $chainMail = new ChainMail();
        $registry = new AbilityRegistry();
        $lookedUpName = null;

        // Register multiple abilities
        $definition1 = new AbilityDefinition(
            name: 'ability-one',
            description: 'First ability',
            parameterSchema: [],
            responseSchema: [],
            handler: function ($p) use (&$lookedUpName) {
                $lookedUpName = 'ability-one';
                return ['id' => 1];
            }
        );

        $definition2 = new AbilityDefinition(
            name: 'ability-two',
            description: 'Second ability',
            parameterSchema: [],
            responseSchema: [],
            handler: function ($p) use (&$lookedUpName) {
                $lookedUpName = 'ability-two';
                return ['id' => 2];
            }
        );

        $registry->register($definition1);
        $registry->register($definition2);

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

        // Create message for ability-two
        $abilityMessage = AbilityMessage::create(
            abilityName: 'ability-two',
            parameters: null
        );

        $action = new Action($region, $abilityMessage);

        // Call dispatch - should lookup and execute ability-two
        $dispatchChain->call($action);

        // Verify correct ability was looked up and executed
        $this->assertEquals(
            'ability-two',
            $lookedUpName,
            'Middleware should lookup definition by message.abilityName'
        );
    }
}
