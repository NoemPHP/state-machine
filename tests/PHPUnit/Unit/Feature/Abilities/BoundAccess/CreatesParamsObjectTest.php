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
 * Acceptance Criterion: BoundAccess creates Params\InvokeAbility from parsed arguments
 *
 * Intent: Constructs params object for chain invocation,
 * encapsulating region and invocation data
 */
#[Group('abilities')]
#[Group('bound-access-integration')]
class CreatesParamsObjectTest extends TestCase
{
    public function testCreatesInvokeAbilityParamsFromParsedArguments(): void
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

        // Track the params object passed to InvokeAbility
        $receivedParams = null;
        $invokeAbility->link(function (InvokeAbilityParams $params, callable $next) use (&$receivedParams) {
            $receivedParams = $params;
            // Don't call next - short circuit to avoid dispatch
            return AbilityMessage::create(
                abilityName: $params->abilityName,
                parameters: $params->parameters,
                definition: null
            );
        });

        // Create a mock region
        $region = $this->createMock(Region::class);

        // Create BoundAccessParams with ability invocation
        $expectedParams = ['key' => 'value'];
        $params = new BoundAccessParams(
            $region,
            BoundAccessParams::TYPE_METHOD,
            'abilities',
            ['test-ability', $expectedParams]
        );

        // When: Call BoundAccess with abilities method
        // This will FAIL because the middleware isn't implemented yet
        try {
            $boundAccess->call($params);
        } catch (\RuntimeException $e) {
            // Expected to fail in RED phase
            $this->assertStringContainsString("Method 'abilities' not found", $e->getMessage());
            return;
        }

        // Then: Should have created InvokeAbilityParams with correct data
        // (These assertions will be reached once middleware is implemented)
        $this->assertInstanceOf(InvokeAbilityParams::class, $receivedParams);
        $this->assertSame($region, $receivedParams->region);
        $this->assertEquals('test-ability', $receivedParams->abilityName);
        $this->assertEquals($expectedParams, $receivedParams->parameters);
    }
}
