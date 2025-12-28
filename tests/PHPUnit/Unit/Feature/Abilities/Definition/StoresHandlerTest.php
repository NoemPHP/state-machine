<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Abilities\Definition;

use Noem\State\Feature\Abilities\AbilityDefinition;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: AbilityDefinition stores handler as readonly callable
 *
 * Intent: Provides execution logic for ability, encapsulating business logic
 * separate from infrastructure
 *
 * Criticality: contract
 */
#[Group('abilities')]
#[Group('ability-definition')]
class StoresHandlerTest extends TestCase
{
    public function testStoresHandlerAsReadonlyCallable(): void
    {
        $name = 'test-ability';
        $description = 'Test ability description';
        $parameterSchema = ['type' => 'object'];
        $responseSchema = ['type' => 'object'];
        $handler = fn(mixed $params): mixed => ['result' => $params];

        $definition = new AbilityDefinition(
            name: $name,
            description: $description,
            parameterSchema: $parameterSchema,
            responseSchema: $responseSchema,
            handler: $handler
        );

        // Verify handler is stored and accessible
        $this->assertSame($handler, $definition->handler);

        // Verify it's callable
        $this->assertIsCallable($definition->handler);

        // Verify readonly behavior - should return same value on multiple accesses
        $firstAccess = $definition->handler;
        $secondAccess = $definition->handler;
        $this->assertSame($firstAccess, $secondAccess, 'Handler should return same value on multiple accesses');

        // Verify handler can be invoked
        $result = ($definition->handler)(['test' => 'data']);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('result', $result);
    }
}
