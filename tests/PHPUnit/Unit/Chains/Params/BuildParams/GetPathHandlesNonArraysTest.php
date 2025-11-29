<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Chains\Params\BuildParams;

use Noem\State\Chains\Params\BuildParams;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: BuildParams.getPath() handles non-array intermediate values gracefully
 */
#[Group('config-accessor'), Group('build-params-infrastructure')]
class GetPathHandlesNonArraysTest extends TestCase
{
    public function testGetPathHandlesStringIntermediateValue(): void
    {
        $builder = new RegionBuilder();
        $config = [
            'a' => 'string_value'
        ];
        $params = new BuildParams($builder, $config);
        
        $result = $params->getPath('a.b.c', 'default');
        
        $this->assertSame('default', $result);
    }
    
    public function testGetPathHandlesNullIntermediateValue(): void
    {
        $builder = new RegionBuilder();
        $config = [
            'a' => null
        ];
        $params = new BuildParams($builder, $config);
        
        $result = $params->getPath('a.b', 'default');
        
        $this->assertSame('default', $result);
    }
}
