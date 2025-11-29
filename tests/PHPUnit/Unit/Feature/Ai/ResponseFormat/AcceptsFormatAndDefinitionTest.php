<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\ResponseFormat;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: ResponseFormat accepts format type and definition
 */
#[Group('ai'), Group('response-format')]
class AcceptsFormatAndDefinitionTest extends TestCase
{
    #[Test]
    public function acceptsFormatAndDefinitionTest(): void
    {
        $this->markTestIncomplete('Spec approved, implementation pending');
    }
}
