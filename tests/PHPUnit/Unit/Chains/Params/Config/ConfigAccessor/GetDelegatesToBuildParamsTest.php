<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Chains\Params\Config\ConfigAccessor;

use Noem\State\Chains\Params\BuildParams;
use Noem\State\Chains\Params\Config\ConfigAccessor;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: ConfigAccessor.get() retrieves values via BuildParams.getPath()
 */
#[Group('config-accessor'), Group('config-accessor-base')]
class GetDelegatesToBuildParamsTest extends TestCase
{
    public function testGetDelegatesToBuildParams(): void
    {
        $builder = new RegionBuilder();
        $config = ['test' => ['nested' => 'value']];
        $params = new BuildParams($builder, $config);
        
        $accessor = $params->config(GetTestAccessor::class);
        
        $this->assertSame('value', $accessor->testGet());
    }
}

class GetTestAccessor extends ConfigAccessor
{
    public function testGet(): mixed
    {
        return $this->get('test.nested');
    }
}
