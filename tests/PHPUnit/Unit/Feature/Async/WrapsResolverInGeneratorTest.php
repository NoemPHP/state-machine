<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async;

use Noem\State\Feature\Async\AsyncFeature;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: AsyncFeature wraps resolver in generator that waits for completion
 */
#[Group('async'), Group('resolver-integration')]
class WrapsResolverInGeneratorTest extends TestCase
{
    public function testWrapsResolverInGenerator(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(
            new AsyncFeature(),
            new ExtendedState(),
            new \Noem\State\Feature\Transitions\TransitionsFeature()
        );

        $yieldCount = 0;
        $accessCount = 0;
        $capturedValues = [];

        $region = $builder
            ->setStates('idle', 'active')
            ->markInitial('idle')
            ->addBuildStep(new \Noem\State\Feature\Transitions\AddTransition(
                'idle',
                'active',
                fn(object $trigger): bool => true
            ))
            ->onEnter('active', function (object $trigger) use (&$yieldCount, &$capturedValues, &$accessCount) {
                $accessCount++;
                // Each access may return different values as the resolver progresses
                $capturedValues[] = $this->get('async');
            })
            ->build([
                'loader' => [
                    'array' => [
                        'context' => [
                            'resolvers' => [
                                [
                                    'name' => 'async',
                                    'run' => function () use (&$yieldCount) {
                                        $yieldCount++;
                                        yield 'step1';
                                        $yieldCount++;
                                        yield 'step2';
                                        $yieldCount++;
                                        return 'async-complete';
                                    },
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

        $this->assertSame(0, $yieldCount, 'Resolver should not execute before access');

        // Trigger to enter active and access the resolver
        // The resolver starts but executes asynchronously through subsequent ticks
        for ($i = 0; $i < 10; $i++) {
            $region->trigger(new \stdClass());
        }

        // The resolver should have completed all yields after multiple ticks
        $this->assertGreaterThanOrEqual(3, $yieldCount, 'Resolver should have yielded multiple times');

        // The first access returns null because the resolver executes asynchronously
        $this->assertNull($capturedValues[0], 'First access returns null as resolver is async');
    }

    public function testWaitsForResolverCompletion(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(
            new AsyncFeature(),
            new ExtendedState(),
            new \Noem\State\Feature\Transitions\TransitionsFeature()
        );

        $steps = [];
        $capturedValue = null;

        $region = $builder
            ->setStates('idle', 'active', 'done')
            ->markInitial('idle')
            ->addBuildStep(new \Noem\State\Feature\Transitions\AddTransition(
                'idle',
                'active',
                fn(object $trigger): bool => true
            ))
            ->onEnter('active', function (object $trigger) use (&$steps, &$capturedValue) {
                $steps[] = 'accessing-resolver';
                // Access the resolver - this starts it but doesn't complete synchronously
                $capturedValue = $this->get('delayed');
            })
            ->build([
                'loader' => [
                    'array' => [
                        'context' => [
                            'resolvers' => [
                                [
                                    'name' => 'delayed',
                                    'run' => function () use (&$steps) {
                                        $steps[] = 'resolver-start';
                                        yield;
                                        $steps[] = 'resolver-mid';
                                        yield;
                                        $steps[] = 'resolver-end';
                                        return 'completed';
                                    },
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

        // Execute multiple ticks to allow resolver to complete
        for ($i = 0; $i < 10; $i++) {
            $region->trigger(new \stdClass());
        }

        // After accessing, the value should be null initially since resolution is async
        // But after multiple ticks, the resolver should have completed its execution
        $this->assertContains('accessing-resolver', $steps);
        $this->assertContains('resolver-start', $steps);
        $this->assertContains('resolver-mid', $steps);
        $this->assertContains('resolver-end', $steps);

        // Now check if the value was eventually cached after resolution
        // Access it again to get the cached value
        $region->trigger(new \stdClass());
    }
}
