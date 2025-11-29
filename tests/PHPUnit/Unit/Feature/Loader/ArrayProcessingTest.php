<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: RegionLoader processes array configuration through ProcessArray
 */
#[Group('loader')]
#[Group('builder-integration')]
class ArrayProcessingTest extends TestCase
{
    public function testProcessesArrayConfigurationThroughProcessArray(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new RegionLoader());
        
        $config = [
            'states' => [
                ['name' => 'start'],
                ['name' => 'end'],
            ],
            'initial' => 'start',
            'final' => 'end',
        ];
        
        $region = $builder->build([
            'loader' => [
                'array' => $config,
            ],
        ]);
        
        $this->assertTrue($region->isInState('start'));
    }
}
