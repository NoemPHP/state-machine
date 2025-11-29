<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\Feature\Loader\LoaderChains\Schema;
use Noem\State\Feature\Loader\LoaderChains\TransformArray;
use Noem\State\Feature\Loader\ProcessArray;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: ProcessArray extracts onExit callbacks from configuration
 */
#[Group('loader')]
#[Group('array-processing')]
class OnExitExtractionTest extends TestCase
{
    public function testExtractsOnExitCallbacksFromConfiguration(): void
    {
        $processor = new ProcessArray(new Schema(), new TransformArray());
        
        $reflection = new \ReflectionClass($processor);
        $method = $reflection->getMethod('extractConfig');
        $method->setAccessible(true);
        
        $statesConfig = [
            [
                'name' => 'active',
                'onExit' => [
                    ['run' => fn($t) => null],
                ],
            ],
        ];
        
        [$states, $regions, $transitions, $callbacks] = $method->invoke($processor, $statesConfig);
        
        $this->assertArrayHasKey('active', $callbacks['onExit']);
        $this->assertCount(1, $callbacks['onExit']['active']);
    }
}
