<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Abilities\Message;

use Noem\State\Feature\Abilities\AbilityMessage;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that AbilityMessage inherits correlationId from Message
 *
 * Spec: ability-message / AbilityMessage accepts optional correlationId in constructor & generates UUID if not provided
 * Intent: Ensures every ability invocation has unique identifier for response matching, inheriting Message behavior
 * Criticality: contract
 */

#[Group('feature')]
#[Group('abilities')]
class InheritsCorrelationIdTest extends TestCase
{
    public function testAcceptsOptionalCorrelationId(): void
    {
        // This test will fail because AbilityMessage doesn't exist yet
        $abilityName = 'test-ability';
        $parameters = ['key' => 'value'];
        $correlationId = 'custom-correlation-id-12345';

        $message = AbilityMessage::create($abilityName, $parameters, null, $correlationId);

        // Should accept and use provided correlation ID
        $this->assertSame($correlationId, $message->correlationId());
    }

    public function testGeneratesUuidIfNotProvided(): void
    {
        // This test will fail because AbilityMessage doesn't exist yet
        $abilityName = 'test-ability';
        $parameters = ['key' => 'value'];

        $message = AbilityMessage::create($abilityName, $parameters);

        // Should generate UUID correlation ID if not provided
        $correlationId = $message->correlationId();
        $this->assertIsString($correlationId);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $correlationId,
            'Generated correlation ID should be a valid UUID'
        );
    }

    public function testInheritsCorrelationIdAccessMethod(): void
    {
        // This test will fail because AbilityMessage doesn't exist yet
        $abilityName = 'test-ability';
        $parameters = ['key' => 'value'];

        $message = AbilityMessage::create($abilityName, $parameters);

        // Should inherit correlationId() method from Message
        $this->assertTrue(
            method_exists($message, 'correlationId'),
            'AbilityMessage should inherit correlationId() method from Message'
        );

        // Should be readonly (same value on multiple calls)
        $id1 = $message->correlationId();
        $id2 = $message->correlationId();
        $this->assertSame($id1, $id2);
    }
}
