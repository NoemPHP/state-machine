<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\ResponseFormat;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: ResponseFormat stores format and definition as readonly properties
 */
#[Group('ai'), Group('response-format')]
class StoresReadonlyPropertiesTest extends TestCase
{
    #[Test]
    public function storesReadonlyPropertiesTest(): void
    {
        $this->markTestIncomplete('Spec approved, implementation pending');
    }
}
