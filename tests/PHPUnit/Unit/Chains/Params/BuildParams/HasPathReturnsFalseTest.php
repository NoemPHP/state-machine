<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Chains\Params\BuildParams;

use Noem\State\Chains\Params\BuildParams;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: BuildParams.hasPath() returns false for non-existent paths
 */
#[Group('config-accessor'), Group('build-params-infrastructure')]
class HasPathReturnsFalseTest extends TestCase
{
    public function testHasPathReturnsFalseWhenPathDoesNotExist(): void
    {
        $builder = new RegionBuilder();
        $config = ['existing' => 'value'];
        $params = new BuildParams($builder, $config);
        
        $this->assertFalse($params->hasPath('non.existent.path'));
    }
}
