<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Chains\Params\BuildParams;

use Noem\State\Chains\Params\BuildParams;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: BuildParams.hasPath() checks if dot notation path exists
 */
#[Group('config-accessor'), Group('build-params-infrastructure')]
class HasPathChecksExistenceTest extends TestCase
{
    public function testHasPathReturnsTrueWhenPathExists(): void
    {
        $builder = new RegionBuilder();
        $config = [
            'loader' => [
                'array' => [
                    'states' => ['a', 'b']
                ]
            ]
        ];
        $params = new BuildParams($builder, $config);
        
        $this->assertTrue($params->hasPath('loader.array.states'));
    }
}
