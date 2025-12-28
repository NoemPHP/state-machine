<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Abilities\Params;

use Noem\State\Feature\Abilities\Chains\Params\InvokeAbility;
use Noem\State\Region;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Params\InvokeAbility stores parameters as readonly mixed
 *
 * Intent: Carries invocation parameters, supporting arbitrary parameter structures
 */
#[Group('abilities')]
#[Group('params')]
class StoresParametersTest extends TestCase
{
    public function testStoresParametersAsReadonlyMixed(): void
    {
        $region = $this->createMock(Region::class);
        $abilityName = 'test-ability';
        $parameters = ['foo' => 'bar', 'baz' => 42];

        $params = new InvokeAbility($region, $abilityName, $parameters);

        $this->assertSame($parameters, $params->parameters);
    }

    public function testSupportsArrayParameters(): void
    {
        $region = $this->createMock(Region::class);
        $parameters = ['key' => 'value'];

        $params = new InvokeAbility($region, 'ability', $parameters);

        $this->assertSame($parameters, $params->parameters);
    }

    public function testSupportsObjectParameters(): void
    {
        $region = $this->createMock(Region::class);
        $parameters = (object)['key' => 'value'];

        $params = new InvokeAbility($region, 'ability', $parameters);

        $this->assertSame($parameters, $params->parameters);
    }

    public function testSupportsScalarParameters(): void
    {
        $region = $this->createMock(Region::class);
        $parameters = 'simple-string';

        $params = new InvokeAbility($region, 'ability', $parameters);

        $this->assertSame($parameters, $params->parameters);
    }

    public function testParametersPropertyIsReadonly(): void
    {
        $region = $this->createMock(Region::class);
        $parameters = ['initial' => 'value'];

        $params = new InvokeAbility($region, 'ability', $parameters);

        // Attempt to modify readonly property should cause error
        $this->expectNotToPerformAssertions();
        $this->expectExceptionMessage('Cannot modify readonly property');

        $params->parameters = ['modified' => 'value'];
    }
}
