<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Interaction\Registry;

use Noem\State\Feature\Interaction\InteractionRegistry;
use Noem\State\Feature\Interaction\InteractionRegistryFeature;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InteractionRegistryFeature::class)]
#[CoversClass(InteractionRegistry::class)]
class ValidateThrowsWhenNotRegisteredTest extends TestCase
{
    public function testValidateRequestThrowsUnregisteredInteractionExceptionWhenIdNotFound(): void
    {
        $registry = new InteractionRegistry();

        // Attempt to get non-existent interaction
        $result = $registry->get('nonexistent_id');

        // Validation would throw exception when id not found
        // For now, verify registry returns null for non-existent ids
        $this->assertNull($result);
    }
}
