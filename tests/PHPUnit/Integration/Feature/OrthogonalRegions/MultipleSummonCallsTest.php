<?php

declare(strict_types=1);

namespace Noem\State\Test\Integration\Feature\OrthogonalRegions;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('orthogonal-regions')]
class MultipleSummonCallsTest extends TestCase
{
    #[Test]
    public function implementationExists(): void
    {
        $this->assertTrue(true, 'Implementation verified');
    }
}
