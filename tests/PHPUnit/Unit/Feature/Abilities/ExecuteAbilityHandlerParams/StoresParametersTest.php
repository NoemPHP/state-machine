<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Abilities\ExecuteAbilityHandlerParams;

use Noem\State\Feature\Abilities\Chains\Params\ExecuteAbilityHandler;
use Noem\State\Region;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Params\ExecuteAbilityHandler stores parameters as readonly mixed
 *
 * Intent: Carries invocation parameters to pass to handler
 */
#[Group('abilities')]
#[Group('params')]
class StoresParametersTest extends TestCase
{
    public function testStoresParametersAsReadonlyProperty(): void
    {
        $handler = fn() => ['result' => 'value'];
        $parameters = ['param' => 'value'];
        $region = $this->createMock(Region::class);

        $params = new ExecuteAbilityHandler($handler, $parameters, $region);

        $this->assertSame($parameters, $params->parameters);
    }
}
