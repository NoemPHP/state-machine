<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Message\StandardMessage;

use Noem\State\Feature\Message\Message;
use Noem\State\Feature\Message\StandardMessage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(StandardMessage::class)]
class ExtendsMessageTest extends TestCase
{
    public function testStandardMessageExtendsMessageBaseClass(): void
    {
        $message = new StandardMessage(['key' => 'value']);

        $this->assertInstanceOf(Message::class, $message);
    }
}
