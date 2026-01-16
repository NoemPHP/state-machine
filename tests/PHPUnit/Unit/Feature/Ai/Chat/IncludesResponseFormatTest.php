<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\Chat;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Chat includes response format in request when specified
 */
#[Group('ai'), Group('chat-api')]
class IncludesResponseFormatTest extends TestCase
{
    #[Test]
    public function includesResponseFormatTest(): void
    {
        $responseFormat = new \Noem\State\Feature\Ai\ResponseFormat('json', ['type' => 'object']);
        $request = (new \Noem\State\Feature\Ai\RequestBuilder())
            ->setPrompt('Test')
            ->setResponseFormat($responseFormat)
            ->build();

        $mockBackend = $this->createMock(\Noem\State\Feature\Ai\Backend\BackendInterface::class);

        $chat = new \Noem\State\Feature\Ai\Chat($request, true, $mockBackend);

        $this->assertInstanceOf(\Noem\State\Feature\Ai\Chat::class, $chat, 'Should support response format in request');
    }
}
