<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Abilities\Message;

use Noem\State\Feature\Abilities\AbilityMessage;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that AbilityMessage stores abilityName as readonly string
 *
 * Spec: ability-message / AbilityMessage stores abilityName as readonly string
 * Intent: Identifies which ability to invoke, enabling handler lookup during dispatch
 * Criticality: contract
 */

#[Group('feature')]
#[Group('abilities')]
class StoresAbilityNameTest extends TestCase
{
    public function testStoresAbilityNameAsReadonlyString(): void
    {
        // This test will fail because AbilityMessage doesn't exist yet
        $abilityName = 'test-ability';
        $parameters = ['key' => 'value'];

        $message = AbilityMessage::create($abilityName, $parameters);

        // Should store abilityName as readonly string
        $this->assertSame($abilityName, $message->abilityName);
        $this->assertIsString($message->abilityName);
    }
}
