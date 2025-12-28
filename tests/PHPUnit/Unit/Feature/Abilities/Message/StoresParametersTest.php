<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Abilities\Message;

use Noem\State\Feature\Abilities\AbilityMessage;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that AbilityMessage stores parameters as readonly mixed
 *
 * Spec: ability-message / AbilityMessage stores parameters as readonly mixed
 * Intent: Carries invocation parameters for ability handler, supporting arbitrary parameter structures
 * Criticality: contract
 */

#[Group('feature')]
#[Group('abilities')]
class StoresParametersTest extends TestCase
{
    public function testStoresParametersAsReadonlyMixed(): void
    {
        // This test will fail because AbilityMessage doesn't exist yet
        $abilityName = 'test-ability';
        $parameters = ['key' => 'value', 'nested' => ['data' => 123]];

        $message = AbilityMessage::create($abilityName, $parameters);

        // Should store parameters as readonly mixed
        $this->assertSame($parameters, $message->parameters);
    }

    public function testStoresNullParameters(): void
    {
        // This test will fail because AbilityMessage doesn't exist yet
        $abilityName = 'test-ability';

        $message = AbilityMessage::create($abilityName, null);

        // Should support null parameters
        $this->assertNull($message->parameters);
    }

    public function testStoresScalarParameters(): void
    {
        // This test will fail because AbilityMessage doesn't exist yet
        $abilityName = 'test-ability';
        $parameters = 'scalar-value';

        $message = AbilityMessage::create($abilityName, $parameters);

        // Should support scalar parameters
        $this->assertSame($parameters, $message->parameters);
    }
}
