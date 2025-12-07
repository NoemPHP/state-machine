<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\Feature\Loader\LoaderChains\Schema;
use Noem\State\Feature\Loader\LoaderChains\TransformArray;
use Noem\State\Feature\Loader\ProcessArray;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: ProcessArray applies states to RegionBuilder
 */
#[Group('loader')]
#[Group('array-processing')]
class StateApplicationTest extends TestCase
{
    public function testAppliesStatesToRegionBuilder(): void
    {
        $processor = new ProcessArray(new Schema(), new TransformArray());

        $config = [
            'states' => [
                ['name' => 'idle'],
                ['name' => 'active'],
            ],
        ];

        $builder = new RegionBuilder();
        $processor->fromData($config, $builder);

        $region = $builder->build();

        $this->assertTrue($region->isInState('idle'));
    }
}
