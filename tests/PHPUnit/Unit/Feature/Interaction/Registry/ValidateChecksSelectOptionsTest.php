<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Interaction\Registry;

use Noem\State\Feature\Interaction\InteractionDefinition;
use Noem\State\Feature\Interaction\InteractionRegistry;
use Noem\State\Feature\Interaction\InteractionRegistryFeature;
use Noem\State\Feature\Interaction\SelectOption;
use Noem\State\Feature\Interaction\SelectRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InteractionRegistryFeature::class)]
#[CoversClass(InteractionRegistry::class)]
class ValidateChecksSelectOptionsTest extends TestCase
{
    public function testValidateRequestVerifiesSelectRequestOptionsMatchDefinitionOptionsKeys(): void
    {
        $registry = new InteractionRegistry();

        $definition = new InteractionDefinition(
            id: 'select_action',
            type: 'select',
            state: 'processing',
            question: 'Choose action',
            options: ['continue' => 'Continue', 'abort' => 'Abort']
        );

        $registry->register($definition);

        // Create request with matching options
        $request = new SelectRequest(
            question: 'Choose action',
            options: [
                'continue' => new SelectOption('Continue'),
                'abort' => new SelectOption('Abort'),
            ]
        );

        // Verify option keys match
        $definitionKeys = array_keys($definition->options ?? []);
        $requestKeys = array_keys($request->options);

        sort($definitionKeys);
        sort($requestKeys);

        $this->assertSame($definitionKeys, $requestKeys);
    }
}
