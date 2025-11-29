<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\Chat;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Chat accepts string prompt and builds Request automatically
 */
#[Group('ai'), Group('chat-api')]
class AcceptsStringPromptTest extends TestCase
{
    #[Test]
    public function acceptsStringPromptTest(): void
    {
        $this->markTestIncomplete('Spec approved, implementation pending');
    }
}
