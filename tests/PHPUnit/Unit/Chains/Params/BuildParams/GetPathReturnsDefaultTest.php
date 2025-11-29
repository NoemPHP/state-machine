<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Chains\Params\BuildParams;

use Noem\State\Chains\Params\BuildParams;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: BuildParams.getPath() returns default value when path does not exist
 */
#[Group('config-accessor'), Group('build-params-infrastructure')]
class GetPathReturnsDefaultTest extends TestCase
{
    public function testGetPathReturnsDefault(): void
    {
        $builder = new RegionBuilder();
        $config = ['existing' => 'value'];
        $params = new BuildParams($builder, $config);
        
        $result = $params->getPath('non.existent.path', 'default_value');
        
        $this->assertSame('default_value', $result);
    }
    
    public function testGetPathReturnsNullByDefault(): void
    {
        $builder = new RegionBuilder();
        $config = [];
        $params = new BuildParams($builder, $config);
        
        $result = $params->getPath('non.existent');
        
        $this->assertNull($result);
    }
}
