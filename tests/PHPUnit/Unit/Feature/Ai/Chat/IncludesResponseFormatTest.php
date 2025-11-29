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
        $this->markTestIncomplete('Spec approved, implementation pending');
    }
}
