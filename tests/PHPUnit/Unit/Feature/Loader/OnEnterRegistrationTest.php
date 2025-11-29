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
 * Acceptance Criterion: ProcessArray registers onEnter callbacks with builder
 */
#[Group('loader')]
#[Group('array-processing')]
class OnEnterRegistrationTest extends TestCase
{
    public function testRegistersOnEnterCallbacksWithBuilder(): void
    {
        $called = false;
        
        $processor = new ProcessArray(new Schema(), new TransformArray());
        
        $config = [
            'states' => [
                [
                    'name' => 'active',
                    'onEnter' => [
                        ['run' => function(object $t) use (&$called) { $called = true; }],
                    ],
                ],
            ],
        ];
        
        $builder = new RegionBuilder();
        $processor->fromData($config, $builder);
        
        $region = $builder->build();
        $region->trigger((object)[]);
        
        $this->assertTrue($called, 'onEnter callback should have been called');
    }
}
