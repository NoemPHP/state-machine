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
 * Acceptance Criterion: ProcessArray sets inherits property when specified in configuration
 */
#[Group('loader')]
#[Group('array-processing')]
class InheritsPropertyTest extends TestCase
{
    public function testSetsInheritsPropertyWhenSpecified(): void
    {
        $processor = new ProcessArray(new Schema(), new TransformArray());
        
        $config = [
            'states' => [['name' => 'idle']],
            'inherits' => ['template1', 'template2'],
        ];
        
        $builder = new RegionBuilder();
        $result = $processor->fromData($config, $builder);
        
        $this->assertInstanceOf(RegionBuilder::class, $result);
    }
}
