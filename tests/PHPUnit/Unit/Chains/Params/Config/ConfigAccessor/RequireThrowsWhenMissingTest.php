<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Chains\Params\Config\ConfigAccessor;

use Noem\State\Chains\Params\BuildParams;
use Noem\State\Chains\Params\Config\ConfigAccessor;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: ConfigAccessor.require() throws RuntimeException when path missing
 */
#[Group('config-accessor'), Group('config-accessor-base')]
class RequireThrowsWhenMissingTest extends TestCase
{
    public function testRequireThrowsWhenMissing(): void
    {
        $builder = new RegionBuilder();
        $config = [];
        $params = new BuildParams($builder, $config);

        $accessor = $params->config(RequireTestAccessor::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Required config missing: non.existent.path');

        $accessor->testRequire();
    }
}

class RequireTestAccessor extends ConfigAccessor
{
    public function testRequire(): mixed
    {
        return $this->require('non.existent.path');
    }
}
