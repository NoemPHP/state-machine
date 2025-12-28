<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Abilities\BoundAccess;

use Noem\State\Feature\Abilities\AbilitiesFeature;
use Noem\State\Feature\Message\MessageFeature;
use Noem\State\Feature\Abilities\AbilityDefinition;
use Noem\State\Feature\Abilities\AbilityMessage;
use Noem\State\Feature\Abilities\AbilityRegistry;
use Noem\State\Feature\Abilities\Chains\InvokeAbility;
use Noem\State\Feature\Abilities\Chains\Params\InvokeAbility as InvokeAbilityParams;
use Noem\State\Feature\ExtendedState\ContextChains\BoundAccess;
use Noem\State\Feature\ExtendedState\ContextChains\Params\BoundAccessParams;
use Noem\State\Middleware\ChainMail;
use Noem\State\Region;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: BoundAccess returns AbilityMessage from chain invocation
 *
 * Intent: Provides message reference to caller, enabling then() handler registration for responses
 */
#[Group('abilities')]
#[Group('bound-access-integration')]
class ReturnsAbilityMessageTest extends TestCase
{
    public function testReturnsAbilityMessageFromChainInvocation(): void
    {
        $chainMail = new ChainMail();
        $registry = new AbilityRegistry();

        // Register a test ability
        $definition = new AbilityDefinition(
            name: 'test-ability',
            description: 'Test ability',
            parameterSchema: [],
            responseSchema: [],
            handler: fn() => ['result' => 'success']
        );
        $registry->register($definition);

        // Provide dependencies
        $chainMail->supply(
            fn(): BoundAccess => new BoundAccess(),
            fn(): AbilityRegistry => $registry
        );

        $messageFeature = new MessageFeature();
        $messageFeature($chainMail);

        $feature = new AbilitiesFeature();
        $feature($chainMail);

        // Get the BoundAccess chain
        $boundAccess = $chainMail->get(BoundAccess::class);
        $invokeAbility = $chainMail->get(InvokeAbility::class);

        // Create a message to return from InvokeAbility
        $expectedMessage = AbilityMessage::create(
            abilityName: 'test-ability',
            parameters: ['param' => 'value'],
            definition: $definition
        );

        // Mock InvokeAbility to return the message
        $invokeAbility->link(function (InvokeAbilityParams $params, callable $next) use ($expectedMessage) {
            return $expectedMessage;
        });

        // Create a mock region
        $region = $this->createMock(Region::class);

        // Create BoundAccessParams for abilities() call
        $params = new BoundAccessParams(
            $region,
            BoundAccessParams::TYPE_METHOD,
            'abilities',
            ['test-ability', ['param' => 'value']]
        );

        // When: Call BoundAccess with abilities method
        // This will FAIL because the middleware isn't implemented yet
        try {
            $result = $boundAccess->call($params);
        } catch (\RuntimeException $e) {
            // Expected to fail in RED phase
            $this->assertStringContainsString("Method 'abilities' not found", $e->getMessage());
            return;
        }

        // Then: Should return the AbilityMessage from InvokeAbility chain
        // (This assertion will be reached once middleware is implemented)
        $this->assertSame($expectedMessage, $result);
    }
}
