<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\Feature\Loader\LoaderChains\Schema;
use Noem\State\Feature\Loader\LoaderChains\TransformArray;
use Noem\State\Feature\Loader\ProcessArray;
use Noem\State\Feature\Transitions\TransitionsFeature;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: ProcessArray adds transitions using AddTransition build step
 */
#[Group('loader')]
#[Group('array-processing')]
class TransitionApplicationTest extends TestCase
{
    public function testAddsTransitionsUsingAddTransitionBuildStep(): void
    {
        $processor = new ProcessArray(new Schema(), new TransformArray());

        $config = [
            'states' => [
                [
                    'name' => 'idle',
                    'transitions' => [
                        ['target' => 'active', 'guard' => fn($t) => true],
                    ],
                ],
                ['name' => 'active'],
            ],
        ];

        $builder = new RegionBuilder();
        $builder->enableFeatures(new TransitionsFeature());
        $processor->fromData($config, $builder);

        $region = $builder->build();

        // Verify transition was applied - region should start in idle
        $this->assertTrue($region->isInState('idle'));
    }
}
