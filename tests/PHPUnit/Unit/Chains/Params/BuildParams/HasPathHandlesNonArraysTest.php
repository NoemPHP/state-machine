<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Chains\Params\BuildParams;

use Noem\State\Chains\Params\BuildParams;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: BuildParams.hasPath() handles non-array intermediate values gracefully
 */
#[Group('config-accessor'), Group('build-params-infrastructure')]
class HasPathHandlesNonArraysTest extends TestCase
{
    public function testHasPathReturnsFalseWhenIntermediateIsString(): void
    {
        $builder = new RegionBuilder();
        $config = [
            'a' => 'string_value'
        ];
        $params = new BuildParams($builder, $config);

        $this->assertFalse($params->hasPath('a.b.c'));
    }
}
