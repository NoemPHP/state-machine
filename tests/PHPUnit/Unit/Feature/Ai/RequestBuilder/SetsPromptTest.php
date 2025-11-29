<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\RequestBuilder;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: RequestBuilder allows setting prompt
 */
#[Group('ai'), Group('request-building')]
class SetsPromptTest extends TestCase
{
    #[Test]
    public function setsPrompt(): void
    {
        $this->markTestIncomplete('Spec approved, implementation pending');
    }
}
