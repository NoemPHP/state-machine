<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\Chat;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Chat formats messages array with roles
 */
#[Group('ai'), Group('chat-api')]
class FormatsMessagesArrayTest extends TestCase
{
    #[Test]
    public function formatsMessagesArrayTest(): void
    {
        $this->markTestIncomplete('Spec approved, implementation pending');
    }
}
