<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\Completion;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Completion accepts pre-built Request object
 */
#[Group('ai'), Group('completion-api')]
class AcceptsRequestObjectTest extends TestCase
{
    #[Test]
    public function acceptsRequestObject(): void
    {
        $this->markTestIncomplete('Spec approved, implementation pending');
    }
}
