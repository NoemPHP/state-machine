<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\Feature\Loader\LoaderChains\Schema;
use Noem\State\Feature\Loader\LoaderChains\TransformArray;
use Noem\State\Feature\Loader\ProcessArray;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: ProcessArray extracts transitions from configuration
 */
#[Group('loader')]
#[Group('array-processing')]
class TransitionExtractionTest extends TestCase
{
    public function testExtractsTransitionsFromConfiguration(): void
    {
        $processor = new ProcessArray(new Schema(), new TransformArray());

        $reflection = new \ReflectionClass($processor);
        $method = $reflection->getMethod('extractConfig');
        $method->setAccessible(true);

        $statesConfig = [
            [
                'name' => 'idle',
                'transitions' => [
                    ['target' => 'active', 'guard' => fn($t) => true],
                ],
            ],
        ];

        [$states, $regions, $transitions, $callbacks] = $method->invoke($processor, $statesConfig);

        $this->assertArrayHasKey('idle', $transitions);
        $this->assertCount(1, $transitions['idle']);
        $this->assertEquals('active', $transitions['idle'][0]['target']);
    }
}
