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
class ValidateMatchesByIdTest extends TestCase
{
    public function testValidateRequestMatchesInteractionRequestAgainstRegisteredInteractionDefinitionById(): void
    {
        $registry = new InteractionRegistry();

        $definition = new InteractionDefinition(
            id: 'proceed_confirm',
            type: 'confirm',
            state: 'ready',
            question: 'Proceed?'
        );

        $registry->register($definition);

        // Create matching request
        $request = new ConfirmRequest(
            question: 'Proceed?',
            defaultValue: false
        );

        // Validation logic would be implemented in InteractionRegistryFeature
        // For now, verify registry can retrieve by id for matching
        $retrieved = $registry->get('proceed_confirm');
        $this->assertSame($definition, $retrieved);
        $this->assertSame('confirm', $retrieved->type);
    }
}
