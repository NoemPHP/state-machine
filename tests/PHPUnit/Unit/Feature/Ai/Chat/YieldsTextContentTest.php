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
        $this->markTestIncomplete('Spec approved, implementation pending');
    }
}
