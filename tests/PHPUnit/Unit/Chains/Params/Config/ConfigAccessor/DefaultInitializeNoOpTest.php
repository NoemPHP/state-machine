<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Chains\Params\Config\ConfigAccessor;

use Noem\State\Chains\Params\BuildParams;
use Noem\State\Chains\Params\Config\ConfigAccessor;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: ConfigAccessor default initialize() does nothing
 */
#[Group('config-accessor'), Group('config-accessor-base')]
class DefaultInitializeNoOpTest extends TestCase
{
    public function testDefaultInitializeDoesNothing(): void
    {
        $builder = new RegionBuilder();
        $config = [];
        $params = new BuildParams($builder, $config);

        // Simply instantiating should not throw or cause side effects
        $accessor = $params->config(NoOpInitializeAccessor::class);

        $this->assertInstanceOf(NoOpInitializeAccessor::class, $accessor);
    }
}

class NoOpInitializeAccessor extends ConfigAccessor
{
    // Uses default initialize() which does nothing
}
