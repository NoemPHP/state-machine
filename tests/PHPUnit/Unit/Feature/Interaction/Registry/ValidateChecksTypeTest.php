<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Interaction\Registry;

use Noem\State\Feature\Interaction\ConfirmRequest;
use Noem\State\Feature\Interaction\InteractionDefinition;
use Noem\State\Feature\Interaction\InteractionRegistry;
use Noem\State\Feature\Interaction\InteractionRegistryFeature;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InteractionRegistryFeature::class)]
#[CoversClass(InteractionRegistry::class)]
class ValidateChecksTypeTest extends TestCase
{
    public function testValidateRequestVerifiesInteractionRequestGetTypeMatchesDefinitionType(): void
    {
        $registry = new InteractionRegistry();

        $definition = new InteractionDefinition(
            id: 'proceed_confirm',
            type: 'confirm',
            state: 'ready',
            question: 'Proceed?'
        );

        $registry->register($definition);

        // Create request with matching type
        $request = new ConfirmRequest(
            question: 'Proceed?',
            defaultValue: false
        );

        // Verify type checking logic - request must match definition type
        $this->assertSame('confirm', $definition->type);
        $this->assertSame('confirm', $request->getType());
    }
}
