<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\Chat;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Chat accepts pre-built Request object
 */
#[Group('ai'), Group('chat-api')]
class AcceptsRequestObjectTest extends TestCase
{
    #[Test]
    public function acceptsRequestObjectTest(): void
    {
        $request = (new \Noem\State\Feature\Ai\RequestBuilder())
            ->setPrompt('Test prompt')
            ->build();

        $mockBackend = $this->createMock(\Noem\State\Feature\Ai\Backend\BackendInterface::class);

        $chat = new \Noem\State\Feature\Ai\Chat($request, true, $mockBackend);

        $this->assertInstanceOf(\Noem\State\Feature\Ai\Chat::class, $chat, 'Should construct with Request object');
    }
}
