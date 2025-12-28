<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Abilities\Params;

use Noem\State\Feature\Abilities\Chains\Params\InvokeAbility;
use Noem\State\Region;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Params\InvokeAbility is immutable after construction
 *
 * Intent: Prevents modification during chain processing, ensuring middleware sees consistent invocation data
 */
#[Group('abilities')]
#[Group('params')]
class ImmutabilityTest extends TestCase
{
    public function testRegionPropertyIsImmutable(): void
    {
        $region = $this->createMock(Region::class);
        $params = new InvokeAbility($region, 'ability');

        $this->expectNotToPerformAssertions();
        $this->expectExceptionMessage('Cannot modify readonly property');

        $params->region = $this->createMock(Region::class);
    }

    public function testAbilityNamePropertyIsImmutable(): void
    {
        $region = $this->createMock(Region::class);
        $params = new InvokeAbility($region, 'ability');

        $this->expectNotToPerformAssertions();
        $this->expectExceptionMessage('Cannot modify readonly property');

        $params->abilityName = 'different-ability';
    }

    public function testParametersPropertyIsImmutable(): void
    {
        $region = $this->createMock(Region::class);
        $params = new InvokeAbility($region, 'ability', ['foo' => 'bar']);

        $this->expectNotToPerformAssertions();
        $this->expectExceptionMessage('Cannot modify readonly property');

        $params->parameters = ['baz' => 'qux'];
    }

    public function testAllPropertiesAreImmutableTogether(): void
    {
        $region = $this->createMock(Region::class);
        $abilityName = 'test-ability';
        $parameters = ['data' => 'value'];

        $params = new InvokeAbility($region, $abilityName, $parameters);

        // Verify all properties remain unchanged
        $this->assertSame($region, $params->region);
        $this->assertSame($abilityName, $params->abilityName);
        $this->assertSame($parameters, $params->parameters);

        // Cannot add new properties
        $this->expectNotToPerformAssertions();

        $params->newProperty = 'value';
    }
}
