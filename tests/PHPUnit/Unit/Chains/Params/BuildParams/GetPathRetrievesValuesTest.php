<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Chains\Params\BuildParams;

use Noem\State\Chains\Params\BuildParams;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: BuildParams.getPath() retrieves values using dot notation paths
 */
#[Group('config-accessor'), Group('build-params-infrastructure')]
class GetPathRetrievesValuesTest extends TestCase
{
    public function testGetPathRetrievesValues(): void
    {
        $builder = new RegionBuilder();
        $config = [
            'loader' => [
                'array' => [
                    'context' => [
                        'resolvers' => ['resolver1', 'resolver2']
                    ]
                ]
            ]
        ];
        $params = new BuildParams($builder, $config);
        
        $result = $params->getPath('loader.array.context.resolvers');
        
        $this->assertSame(['resolver1', 'resolver2'], $result);
    }
    
    public function testGetPathHandlesNestedPaths(): void
    {
        $builder = new RegionBuilder();
        $config = [
            'a' => [
                'b' => [
                    'c' => 'value'
                ]
            ]
        ];
        $params = new BuildParams($builder, $config);
        
        $this->assertSame('value', $params->getPath('a.b.c'));
    }
}
