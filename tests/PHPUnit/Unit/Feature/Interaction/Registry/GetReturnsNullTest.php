<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Interaction\Registry;

use Noem\State\Feature\Interaction\InteractionRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InteractionRegistry::class)]
class GetReturnsNullTest extends TestCase
{
    public function testReturnsNullWhenIdDoesNotExist(): void
    {
        $registry = new InteractionRegistry();

        $result = $registry->get('non_existent_id');

        $this->assertNull($result);
    }

    public function testReturnsNullForEmptyRegistry(): void
    {
        $registry = new InteractionRegistry();

        $this->assertNull($registry->get('any_id'));
    }
}
