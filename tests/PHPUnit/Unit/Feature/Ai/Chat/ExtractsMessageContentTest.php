<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\Chat;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Chat extracts text from message content when asText is true
 */
#[Group('ai'), Group('chat-api')]
class ExtractsMessageContentTest extends TestCase
{
    #[Test]
    public function extractsMessageContentTest(): void
    {
        $this->markTestIncomplete('Spec approved, implementation pending');
    }
}
