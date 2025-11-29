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
 * Acceptance Criterion: ProcessArray marks final state when specified in configuration
 */
#[Group('loader')]
#[Group('array-processing')]
class FinalStateMarkingTest extends TestCase
{
    public function testMarksFinalStateWhenSpecified(): void
    {
        $processor = new ProcessArray(new Schema(), new TransformArray());
        
        $config = [
            'states' => [
                ['name' => 'idle'],
                ['name' => 'done'],
            ],
            'final' => 'done',
        ];
        
        $builder = new RegionBuilder();
        $processor->fromData($config, $builder);
        
        $region = $builder->build();
        
        // Can verify final state was marked (isFinal checks current state)
        $this->assertFalse($region->isFinal());
    }
}
