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
 * Acceptance Criterion: Params\ProcessAbilityResult stores handlerResult as readonly mixed
 *
 * Intent: Carries handler return value (array or Task), enabling async vs sync discrimination
 */
#[Group('abilities')]
#[Group('params')]
class StoresHandlerResultTest extends TestCase
{
    public function testStoresHandlerResultAsReadonlyProperty(): void
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

        $this->assertSame($handlerResult, $params->handlerResult);
    }
}
