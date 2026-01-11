<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Interaction\Registry;

use Noem\State\Feature\Interaction\InteractionRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InteractionRegistry::class)]
class ProvidesGetTriggeredTest extends TestCase
{
    public function testProvidesGetTriggeredMethodReturningArrayOfTriggeredIds(): void
    {
        $registry = new InteractionRegistry();

        $triggered = $registry->getTriggered();

        $this->assertIsArray($triggered);
    }

    public function testGetTriggeredMethodExists(): void
    {
        $this->assertTrue(method_exists(InteractionRegistry::class, 'getTriggered'));
    }
}
