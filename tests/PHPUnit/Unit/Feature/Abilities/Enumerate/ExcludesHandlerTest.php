<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Abilities\Enumerate;

use Noem\State\Chains\DispatchAction;
use Noem\State\Chains\ConnectedRegions;
use Noem\State\Events;
use Noem\State\Chains\Notification;
use Noem\State\Feature\Abilities\AbilitiesFeature;
use Noem\State\Feature\Abilities\AbilityDefinition;
use Noem\State\Feature\Abilities\AbilityRegistry;
use Noem\State\Middleware\ChainMail;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: enumerate-abilities handler excludes handler callable from response
 *
 * Intent: Prevents non-serializable callable exposure in enumeration, maintaining clean response structure
 */
#[Group('abilities')]
#[Group('enumerate-abilities-builtin')]
class ExcludesHandlerTest extends TestCase
{
    #[Test]
    public function responseExcludesHandlerCallable(): void
    {
        $chainMail = new ChainMail();

        $chainMail->supply(
            fn(): DispatchAction => new DispatchAction(new ConnectedRegions(), new Events(new \Noem\State\Chains\ValidateCallback(), new \Noem\State\Chains\PrepareInvokable(), new \Noem\State\Chains\InvokeCallback(), new \Noem\State\Callbacks\CallbackRegistry())),
            fn(): Notification => new Notification(),
            fn(): AbilityRegistry => new AbilityRegistry()
        );

        $feature = new AbilitiesFeature();
        $feature($chainMail);

        $registry = $chainMail->get(AbilityRegistry::class);

        // Register a custom ability with a handler
        $customAbility = new AbilityDefinition(
            name: 'test-ability',
            description: 'Test ability',
            parameterSchema: [],
            responseSchema: [],
            handler: fn() => ['result' => 'test']
        );
        $registry->register($customAbility);

        // Get the enumerate-abilities ability
        $enumerateAbility = $registry->get('enumerate-abilities');
        $handler = $enumerateAbility->handler;

        // Execute the handler
        $result = $handler(null);
        $abilities = $result['abilities'];

        // Find the test ability in results
        $testAbility = null;
        foreach ($abilities as $ability) {
            if ($ability['name'] === 'test-ability') {
                $testAbility = $ability;
                break;
            }
        }

        $this->assertNotNull(
            $testAbility,
            'test-ability should be in enumeration results'
        );

        // Verify handler is NOT included
        $this->assertArrayNotHasKey(
            'handler',
            $testAbility,
            'Handler callable should be excluded from response (not serializable)'
        );

        // Verify the response is serializable
        $jsonEncoded = json_encode($result);
        $this->assertNotFalse(
            $jsonEncoded,
            'Response should be JSON-serializable'
        );

        $decoded = json_decode($jsonEncoded, true);
        $this->assertSame(
            $result,
            $decoded,
            'Response should survive JSON round-trip'
        );
    }
}
