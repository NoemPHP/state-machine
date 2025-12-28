<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Abilities\ExecuteAbilityHandlerParams;

use Noem\State\Feature\Abilities\Chains\Params\ExecuteAbilityHandler;
use Noem\State\Region;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Params\ExecuteAbilityHandler stores handler as readonly callable
 *
 * Intent: Carries ability handler for execution
 */
#[Group('abilities')]
#[Group('params')]
class StoresHandlerTest extends TestCase
{
    public function testStoresHandlerAsReadonlyProperty(): void
    {
        $handler = fn() => ['result' => 'value'];
        $parameters = ['param' => 'value'];
        $region = $this->createMock(Region::class);

        $params = new ExecuteAbilityHandler($handler, $parameters, $region);

        $this->assertSame($handler, $params->handler);
    }
}
