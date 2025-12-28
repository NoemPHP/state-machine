<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Abilities\ExecuteAbilityHandlerParams;

use Noem\State\Feature\Abilities\Chains\Params\ExecuteAbilityHandler;
use Noem\State\Region;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Params\ExecuteAbilityHandler stores region as readonly Region
 *
 * Intent: Provides region context for handler execution
 */
#[Group('abilities')]
#[Group('params')]
class StoresRegionTest extends TestCase
{
    public function testStoresRegionAsReadonlyProperty(): void
    {
        $handler = fn() => ['result' => 'value'];
        $parameters = ['param' => 'value'];
        $region = $this->createMock(Region::class);

        $params = new ExecuteAbilityHandler($handler, $parameters, $region);

        $this->assertSame($region, $params->region);
    }
}
