<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\Completion;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Completion yields raw JSON responses when asText is false
 */
#[Group('ai'), Group('completion-api')]
class YieldsRawJsonTest extends TestCase
{
    #[Test]
    public function yieldsRawJsonTest(): void
    {
        $this->markTestIncomplete('Spec approved, implementation pending');
    }
}
