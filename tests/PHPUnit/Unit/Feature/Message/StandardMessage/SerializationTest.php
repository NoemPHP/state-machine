<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Message\StandardMessage;

use Noem\State\Feature\Message\Message;
use Noem\State\Feature\Message\StandardMessage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(StandardMessage::class)]
class SerializationTest extends TestCase
{
    public function testSerializesToJson(): void
    {
        $data = [
            'name' => 'Test',
            'count' => 42,
            'active' => true,
        ];

        $message = new StandardMessage($data);

        $serialized = json_encode($message);
        $decoded = json_decode($serialized, true);

        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('correlationId', $decoded);
        $this->assertArrayHasKey('type', $decoded);
        $this->assertArrayHasKey('data', $decoded);
        $this->assertSame('StandardMessage', $decoded['type']);
        $this->assertSame($data, $decoded['data']);
    }

    public function testReconstructsViaFromData(): void
    {
        $data = [
            'name' => 'Test',
            'count' => 42,
        ];
        $correlationId = 'test-correlation-id';

        // fromData is protected, so reconstruct via fromJson
        $jsonData = [
            'type' => StandardMessage::class,
            'correlationId' => $correlationId,
            'data' => $data,
        ];

        $message = Message::fromJson($jsonData);

        $this->assertInstanceOf(StandardMessage::class, $message);
        $this->assertSame($correlationId, $message->correlationId());
        $this->assertSame('Test', $message->get('name'));
        $this->assertSame(42, $message->get('count'));
    }

    public function testRoundTripSerialization(): void
    {
        $originalData = [
            'key1' => 'value1',
            'key2' => 123,
            'nested' => ['a' => 'b'],
        ];

        $original = new StandardMessage($originalData);
        $serialized = json_encode($original);
        $decoded = json_decode($serialized, true);

        $reconstructed = Message::fromJson($decoded);

        $this->assertSame($original->correlationId(), $reconstructed->correlationId());
        $this->assertSame($originalData, $decoded['data']);
        $this->assertSame('value1', $reconstructed->get('key1'));
        $this->assertSame(123, $reconstructed->get('key2'));
        $this->assertSame(['a' => 'b'], $reconstructed->get('nested'));
    }
}
