<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Message\StandardMessage;

use Noem\State\Feature\Message\Message;
use Noem\State\Feature\Message\StandardMessage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(StandardMessage::class)]
#[CoversClass(Message::class)]
class FallbackHydrationTest extends TestCase
{
    public function testFromJsonReturnsStandardMessageForUnknownType(): void
    {
        $data = [
            'type' => 'UnknownMessageType',
            'correlationId' => 'test-id',
            'data' => ['key' => 'value'],
        ];

        $message = Message::fromJson($data);

        $this->assertInstanceOf(StandardMessage::class, $message);
        $this->assertSame('test-id', $message->correlationId());
        $this->assertSame('value', $message->get('key'));
    }

    public function testFromJsonReturnsStandardMessageForMissingType(): void
    {
        $data = [
            'correlationId' => 'test-id',
            'data' => ['key' => 'value'],
        ];

        $message = Message::fromJson($data);

        $this->assertInstanceOf(StandardMessage::class, $message);
        $this->assertSame('test-id', $message->correlationId());
        $this->assertSame('value', $message->get('key'));
    }

    public function testFromJsonHandlesNullData(): void
    {
        $data = [
            'type' => 'UnknownType',
            'correlationId' => 'test-id',
        ];

        $message = Message::fromJson($data);

        $this->assertInstanceOf(StandardMessage::class, $message);
        $this->assertNull($message->get('anyKey'));
    }
}
