<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Message\StandardMessage;

use Noem\State\Feature\Message\StandardMessage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(StandardMessage::class)]
class DataAccessTest extends TestCase
{
    public function testStoresAndExposesArbitraryArrayData(): void
    {
        $data = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'meta' => [
                'age' => 30,
                'active' => true,
            ],
        ];

        $message = new StandardMessage($data);

        $this->assertSame('Test User', $message->get('name'));
        $this->assertSame('test@example.com', $message->get('email'));
        $this->assertSame(['age' => 30, 'active' => true], $message->get('meta'));
    }

    public function testGetReturnsNullForNonExistentKey(): void
    {
        $message = new StandardMessage(['existing' => 'value']);

        $this->assertNull($message->get('nonExistent'));
    }

    public function testGetReturnsDefaultForNonExistentKey(): void
    {
        $message = new StandardMessage(['existing' => 'value']);

        $this->assertSame('default', $message->get('nonExistent', 'default'));
    }
}
