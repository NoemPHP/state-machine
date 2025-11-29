<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\Feature\Loader\LoaderChains\Schema;
use Noem\State\Feature\Loader\LoaderChains\TransformArray;
use Noem\State\Feature\Loader\ProcessArray;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: ProcessArray extracts action callbacks from configuration
 */
#[Group('loader')]
#[Group('array-processing')]
class ActionExtractionTest extends TestCase
{
    public function testExtractsActionCallbacksFromConfiguration(): void
    {
        $processor = new ProcessArray(new Schema(), new TransformArray());
        
        $reflection = new \ReflectionClass($processor);
        $method = $reflection->getMethod('extractConfig');
        $method->setAccessible(true);
        
        $statesConfig = [
            [
                'name' => 'processing',
                'action' => [
                    ['run' => fn($t) => null],
                ],
            ],
        ];
        
        [$states, $regions, $transitions, $callbacks] = $method->invoke($processor, $statesConfig);
        
        $this->assertArrayHasKey('processing', $callbacks['action']);
        $this->assertCount(1, $callbacks['action']['processing']);
    }
}
