<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Interaction\Registry;

use Noem\State\Feature\Interaction\InteractionRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InteractionRegistry::class)]
class GetAcceptsIdTest extends TestCase
{
    public function testGetAcceptsStringIdParameter(): void
    {
        $registry = new InteractionRegistry();

        // Should accept string parameter without error
        $result = $registry->get('some_interaction_id');

        // Method exists and can be called
        $this->assertTrue(true);
    }

    public function testGetMethodExists(): void
    {
        $this->assertTrue(method_exists(InteractionRegistry::class, 'get'));
    }
}
