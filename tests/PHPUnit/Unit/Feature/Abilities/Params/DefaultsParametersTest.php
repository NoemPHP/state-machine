<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Abilities\Params;

use Noem\State\Feature\Abilities\Chains\Params\InvokeAbility;
use Noem\State\Region;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Params\InvokeAbility defaults parameters to null if not provided
 *
 * Intent: Enables parameterless ability invocations with clean API, supporting abilities without required parameters
 */
#[Group('abilities')]
#[Group('params')]
class DefaultsParametersTest extends TestCase
{
    public function testParametersDefaultToNull(): void
    {
        $region = $this->createMock(Region::class);
        $abilityName = 'parameterless-ability';

        // Construct without providing parameters argument
        $params = new InvokeAbility($region, $abilityName);

        $this->assertNull($params->parameters);
    }

    public function testNullParametersCanBeExplicitlyProvided(): void
    {
        $region = $this->createMock(Region::class);
        $abilityName = 'ability';

        // Explicitly pass null
        $params = new InvokeAbility($region, $abilityName, null);

        $this->assertNull($params->parameters);
    }
}
