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
        $this->markTestIncomplete('Spec approved, implementation pending');
    }
}
