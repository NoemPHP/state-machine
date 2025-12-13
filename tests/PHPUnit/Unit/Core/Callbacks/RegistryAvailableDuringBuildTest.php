<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Core\Callbacks;

use Noem\State\Callbacks\AddCallback;
use Noem\State\Callbacks\CallbackRegistry;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Test: CallbackRegistry is available during BuildStep.callback() execution
 *
 * Intent: Ensures registry is accessible when AddCallback registers callbacks
 * during build pipeline
 */
#[CoversClass(CallbackRegistry::class)]
#[CoversClass(AddCallback::class)]
final class RegistryAvailableDuringBuildTest extends TestCase
{
    public function testRegistryIsAvailableDuringBuildStepExecution(): void
    {
        $registry = new CallbackRegistry();
        $builder = new RegionBuilder();
        $builder->chainMail->supply(fn(): CallbackRegistry => $registry);

        $callback = fn() => 'test';
        $buildExecuted = false;

        // Create a custom BuildStep that verifies registry availability
        $customStep = new class ($registry, $buildExecuted) implements \Noem\State\BuildStep {
            private bool $buildExecuted;

            public function __construct(
                private CallbackRegistry $expectedRegistry,
                bool &$buildExecuted
            ) {
                $this->buildExecuted = &$buildExecuted;
            }

            public function callback(
                \Noem\State\RegionBuilder $builder,
                callable $next,
                callable $first
            ): \Noem\State\Region {
                // Verify registry is available during build
                $registry = $builder->chainMail->get(CallbackRegistry::class);
                \PHPUnit\Framework\Assert::assertSame(
                    $this->expectedRegistry,
                    $registry,
                    'Registry should be available during BuildStep execution'
                );
                $this->buildExecuted = true;

                return $next($builder);
            }
        };

        $builder
            ->addState('idle')
            ->markInitial('idle')
            ->addBuildStep($customStep)
            ->build([]);

        $this->assertTrue($buildExecuted, 'Build step should have executed');
    }

    public function testMultipleBuildStepsCanAccessSameRegistry(): void
    {
        $registry = new CallbackRegistry();
        $builder = new RegionBuilder();
        $builder->chainMail->supply(fn(): CallbackRegistry => $registry);

        $callback1 = fn() => 'first';
        $callback2 = fn() => 'second';

        $builder
            ->addState('idle')
            ->markInitial('idle')
            ->addBuildStep(
                new AddCallback(
                    event: 'action',
                    state: 'idle',
                    callback: $callback1
                )
            )
            ->addBuildStep(
                new AddCallback(
                    event: 'enter',
                    state: 'idle',
                    callback: $callback2
                )
            )
            ->build([]);

        // Both build steps accessed the same registry
        $records = $registry->query();
        $this->assertCount(2, $records);
    }
}
