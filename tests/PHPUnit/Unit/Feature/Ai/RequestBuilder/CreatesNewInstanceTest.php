<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\RequestBuilder;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: RequestBuilder supports creating new instance with current configuration
 */
#[Group('ai'), Group('request-building')]
class CreatesNewInstanceTest extends TestCase
{
    #[Test]
    public function createsNewInstance(): void
    {
        $this->markTestIncomplete('Spec approved, implementation pending');
    }
}
