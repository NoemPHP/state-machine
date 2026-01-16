<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\Chat;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Chat yields text content from responses
 */
#[Group('ai'), Group('chat-api')]
class YieldsTextContentTest extends TestCase
{
    #[Test]
    public function yieldsTextContentTest(): void
    {
        $mockBackend = $this->createMock(\Noem\State\Feature\Ai\Backend\BackendInterface::class);
        $mockBackend->method('stream')
            ->willReturn((function () {
                yield ['choices' => [['message' => ['content' => 'Hello ']]]];
                yield ['choices' => [['message' => ['content' => 'world']]]];
            })());

        $chat = new \Noem\State\Feature\Ai\Chat('Test', true, $mockBackend);

        $result = iterator_to_array($chat());

        $this->assertSame(['Hello ', 'world'], $result, 'Should yield text content from message field');
    }
}
