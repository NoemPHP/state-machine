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
 * Acceptance Criterion: ProcessArray extracts states from configuration
 */
#[Group('loader')]
#[Group('array-processing')]
class StateExtractionTest extends TestCase
{
    public function testExtractsStatesFromConfiguration(): void
    {
        $schema = new Schema();
        $transformArray = new TransformArray();
        $processor = new ProcessArray($schema, $transformArray);

        $config = [
            'states' => [
                ['name' => 'idle'],
                ['name' => 'active'],
                ['name' => 'done'],
            ],
        ];

        $builder = new RegionBuilder();
        $processor->fromData($config, $builder);

        $region = $builder->build();

        // Verify states were extracted and applied - region starts in first state
        $this->assertTrue($region->isInState('idle'));
    }
}
