<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Abilities\Message;

use Noem\State\Feature\Abilities\AbilityMessage;
use Noem\State\Feature\Message\Message;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that AbilityMessage extends Message base class
 *
 * Spec: ability-message / AbilityMessage extends Message base class
 * Intent: Inherits correlation infrastructure from Message, enabling request-response pattern through MessageFeature
 * Criticality: contract
 */

#[Group('feature')]
#[Group('abilities')]
class ExtendsMessageTest extends TestCase
{
    public function testAbilityMessageExtendsMessageBaseClass(): void
    {
        // This test will fail because AbilityMessage doesn't exist yet
        $this->assertTrue(
            is_subclass_of(AbilityMessage::class, Message::class),
            'AbilityMessage must extend Message base class'
        );
    }
}
