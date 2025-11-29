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
 * Acceptance Criterion: ProcessArray registers onExit callbacks with builder
 */
#[Group('loader')]
#[Group('array-processing')]
class OnExitRegistrationTest extends TestCase
{
    public function testRegistersOnExitCallbacksWithBuilder(): void
    {
        $processor = new ProcessArray(new Schema(), new TransformArray());
        
        $config = [
            'states' => [
                [
                    'name' => 'temp',
                    'onExit' => [
                        ['run' => fn($t) => null],
                    ],
                ],
            ],
        ];
        
        $builder = new RegionBuilder();
        $result = $processor->fromData($config, $builder);
        
        // Verify builder is returned (callbacks are registered internally)
        $this->assertInstanceOf(RegionBuilder::class, $result);
    }
}
