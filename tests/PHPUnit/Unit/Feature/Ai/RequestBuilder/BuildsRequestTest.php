<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\RequestBuilder;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: RequestBuilder builds immutable Request object
 */
#[Group('ai'), Group('request-building')]
class BuildsRequestTest extends TestCase
{
    #[Test]
    public function buildsRequest(): void
    {
        $this->markTestIncomplete('Spec approved, implementation pending');
    }
}
