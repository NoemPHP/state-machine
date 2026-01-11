<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Interaction\Registry;

use Noem\State\Feature\Interaction\InteractionRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InteractionRegistry::class)]
class GetByStateAcceptsStateTest extends TestCase
{
    public function testGetByStateAcceptsStringStateParameter(): void
    {
        $registry = new InteractionRegistry();

        // Should accept string parameter without error
        $result = $registry->getByState('some_state');

        $this->assertTrue(true);
    }

    public function testGetByStateMethodExists(): void
    {
        $this->assertTrue(method_exists(InteractionRegistry::class, 'getByState'));
    }
}
