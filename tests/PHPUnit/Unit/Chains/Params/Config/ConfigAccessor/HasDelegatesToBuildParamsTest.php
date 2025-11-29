<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Chains\Params\Config\ConfigAccessor;

use Noem\State\Chains\Params\BuildParams;
use Noem\State\Chains\Params\Config\ConfigAccessor;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: ConfigAccessor.has() checks existence via BuildParams.hasPath()
 */
#[Group('config-accessor'), Group('config-accessor-base')]
class HasDelegatesToBuildParamsTest extends TestCase
{
    public function testHasDelegatesToBuildParams(): void
    {
        $builder = new RegionBuilder();
        $config = ['test' => 'value'];
        $params = new BuildParams($builder, $config);
        
        $accessor = $params->config(HasTestAccessor::class);
        
        $this->assertTrue($accessor->testHas());
        $this->assertFalse($accessor->testHasNonExistent());
    }
}

class HasTestAccessor extends ConfigAccessor
{
    public function testHas(): bool
    {
        return $this->has('test');
    }
    
    public function testHasNonExistent(): bool
    {
        return $this->has('nonexistent');
    }
}
