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
 * Acceptance Criterion: Params\ProcessAbilityResult stores message as readonly AbilityMessage
 *
 * Intent: Carries request message for correlation, enabling response delivery to correct then() handlers
 */
#[Group('abilities')]
#[Group('params')]
class StoresMessageTest extends TestCase
{
    public function testStoresMessageAsReadonlyProperty(): void
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

        $this->assertSame($message, $params->message);
    }
}
