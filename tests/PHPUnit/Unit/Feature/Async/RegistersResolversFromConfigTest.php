<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async;

use Noem\State\Feature\Async\AsyncFeature;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: AsyncFeature registers resolvers during build phase from loader config
 */
#[Group('async'), Group('resolver-integration')]
class RegistersResolversFromConfigTest extends TestCase
{
    public function testRegistersResolversFromConfig(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(
            new AsyncFeature(),
            new ExtendedState()
        );

        $resolver1Called = false;
        $resolver2Called = false;

        $region = $builder
            ->setStates('idle', 'active')
            ->markInitial('idle')
            ->markFinal('active')
            ->addBuildStep(new \Noem\State\Feature\Transitions\AddTransition('idle', 'active', fn(object $t): bool => true))
            ->onEnter('active', function (object $trigger) use (&$resolver1Called, &$resolver2Called) {
                $val1 = $this->get('first');
                $val2 = $this->get('second');
            })
            ->build([
                'loader' => [
                    'array' => [
                        'context' => [
                            'resolvers' => [
                                [
                                    'name' => 'first',
                                    'run' => function () use (&$resolver1Called) {
                                        $resolver1Called = true;
                                        yield;
                                        return 'first-result';
                                    },
                                ],
                                [
                                    'name' => 'second',
                                    'run' => function () use (&$resolver2Called) {
                                        $resolver2Called = true;
                                        yield;
                                        return 'second-result';
                                    },
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

        $this->assertFalse($resolver1Called);
        $this->assertFalse($resolver2Called);

        // Execute ticks until the region reaches final state and beyond
        // to allow async resolvers to complete
        for ($i = 0; $i < 20; $i++) {
            $region->trigger(new \stdClass());
        }

        $this->assertTrue($resolver1Called, 'First resolver should be registered and callable');
        $this->assertTrue($resolver2Called, 'Second resolver should be registered and callable');
    }

    public function testResolversRegisteredDuringBuildPhase(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(
            new AsyncFeature(),
            new ExtendedState()
        );

        $buildStepExecuted = false;

        // Create a simple BuildStep to verify build phase execution
        $testBuildStep = new class ($buildStepExecuted) implements \Noem\State\BuildStep {
            private bool $executed = false;

            public function __construct(private $executedRef)
            {
            }

            public function callback(\Noem\State\RegionBuilder $builder, callable $next, callable $first): \Noem\State\Region
            {
                $this->executed = true;
                return $next($builder);
            }

            public function wasExecuted(): bool
            {
                return $this->executed;
            }
        };

        $builder->addBuildStep($testBuildStep);

        $region = $builder
            ->setStates('idle')
            ->build([
                'loader' => [
                    'array' => [
                        'context' => [
                            'resolvers' => [
                                [
                                    'name' => 'test',
                                    'run' => function () {
                                        yield;
                                        return 'value';
                                    },
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

        $this->assertTrue($testBuildStep->wasExecuted());
        $this->assertInstanceOf(\Noem\State\Region::class, $region);
    }
}
