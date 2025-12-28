<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Abilities\Params;

use Noem\State\Feature\Abilities\Chains\Params\InvokeAbility;
use Noem\State\Region;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Params\InvokeAbility stores region as readonly property
 *
 * Intent: Provides target region for ability invocation, enabling handler execution in correct region context
 */
#[Group('abilities')]
#[Group('params')]
class StoresRegionTest extends TestCase
{
    public function testStoresRegionAsReadonlyProperty(): void
    {
        $region = $this->createMock(Region::class);
        $abilityName = 'test-ability';

        $params = new InvokeAbility($region, $abilityName);

        $this->assertSame($region, $params->region);
    }

    public function testRegionPropertyIsReadonly(): void
    {
        $region = $this->createMock(Region::class);
        $abilityName = 'test-ability';

        $params = new InvokeAbility($region, $abilityName);

        // Attempt to modify readonly property should cause error
        $this->expectNotToPerformAssertions();
        $this->expectExceptionMessage('Cannot modify readonly property');

        $params->region = $this->createMock(Region::class);
    }
}
