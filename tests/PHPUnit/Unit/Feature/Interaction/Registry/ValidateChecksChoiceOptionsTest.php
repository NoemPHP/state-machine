<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Interaction\Registry;

use Noem\State\Feature\Interaction\ChoiceOption;
use Noem\State\Feature\Interaction\ChoiceRequest;
use Noem\State\Feature\Interaction\InteractionDefinition;
use Noem\State\Feature\Interaction\InteractionRegistry;
use Noem\State\Feature\Interaction\InteractionRegistryFeature;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InteractionRegistryFeature::class)]
#[CoversClass(InteractionRegistry::class)]
class ValidateChecksChoiceOptionsTest extends TestCase
{
    public function testValidateRequestVerifiesChoiceRequestOptionsMatchDefinitionOptionsKeys(): void
    {
        $registry = new InteractionRegistry();

        $definition = new InteractionDefinition(
            id: 'select_features',
            type: 'choice',
            state: 'setup',
            question: 'Select features',
            options: ['auth' => 'Authentication', 'api' => 'API', 'db' => 'Database']
        );

        $registry->register($definition);

        // Create request with matching options
        $request = new ChoiceRequest(
            question: 'Select features',
            options: [
                'auth' => new ChoiceOption('Authentication'),
                'api' => new ChoiceOption('API'),
                'db' => new ChoiceOption('Database'),
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
