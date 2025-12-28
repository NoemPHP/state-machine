<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Abilities\Params;

use Noem\State\Feature\Abilities\Chains\Params\InvokeAbility;
use Noem\State\Region;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Params\InvokeAbility stores abilityName as readonly string
 *
 * Intent: Identifies which ability to invoke, encapsulating invocation intent in params object
 */
#[Group('abilities')]
#[Group('params')]
class StoresAbilityNameTest extends TestCase
{
    public function testStoresAbilityNameAsReadonlyString(): void
    {
        $region = $this->createMock(Region::class);
        $abilityName = 'calculate-sum';

        $params = new InvokeAbility($region, $abilityName);

        $this->assertSame($abilityName, $params->abilityName);
    }

    public function testAbilityNamePropertyIsReadonly(): void
    {
        $region = $this->createMock(Region::class);
        $abilityName = 'test-ability';

        $params = new InvokeAbility($region, $abilityName);

        // Attempt to modify readonly property should cause error
        $this->expectNotToPerformAssertions();
        $this->expectExceptionMessage('Cannot modify readonly property');

        $params->abilityName = 'different-ability';
    }
}
