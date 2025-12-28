<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Abilities\ProcessAbilityResultParams;

use Noem\State\Feature\Abilities\AbilityDefinition;
use Noem\State\Feature\Abilities\AbilityMessage;
use Noem\State\Feature\Abilities\Chains\Params\ProcessAbilityResult;
use Noem\State\Region;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Params\ProcessAbilityResult stores definition as readonly AbilityDefinition
 *
 * Intent: Provides definition metadata for response schema validation
 */
#[Group('abilities')]
#[Group('params')]
class StoresDefinitionTest extends TestCase
{
    public function testStoresDefinitionAsReadonlyProperty(): void
    {
        $definition = new AbilityDefinition(
            name: 'test',
            description: 'Test',
            parameterSchema: [],
            responseSchema: [],
            handler: fn() => []
        );
        $message = AbilityMessage::create('test', [], $definition);
        $handlerResult = ['result' => 'value'];
        $region = $this->createMock(Region::class);

        $params = new ProcessAbilityResult($message, $handlerResult, $definition, $region);

        $this->assertSame($definition, $params->definition);
    }
}
