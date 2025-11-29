<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Chains\Params\Config\ConfigAccessor;

use Noem\State\Chains\Params\BuildParams;
use Noem\State\Chains\Params\Config\ConfigAccessor;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: ConfigAccessor.require() returns value when path exists
 */
#[Group('config-accessor'), Group('config-accessor-base')]
class RequireReturnsValueTest extends TestCase
{
    public function testRequireReturnsValue(): void
    {
        $builder = new RegionBuilder();
        $config = ['test' => 'expected_value'];
        $params = new BuildParams($builder, $config);
        
        $accessor = $params->config(RequireValueTestAccessor::class);
        
        $this->assertSame('expected_value', $accessor->testRequire());
    }
}

class RequireValueTestAccessor extends ConfigAccessor
{
    public function testRequire(): mixed
    {
        return $this->require('test');
    }
}
