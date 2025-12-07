<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async;

use Noem\State\Feature\Async\AsyncFeature;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: AsyncFeature creates ornaments for all region resolvers
 */
#[Group('async'), Group('resolver-integration')]
class CreatesOrnamentsForResolversTest extends TestCase
{
    public function testCreatesOrnamentsForResolvers(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(
            new AsyncFeature(),
            new ExtendedState(),
            new \Noem\State\Feature\Transitions\TransitionsFeature()
        );

        $resolverCalled = false;

        $callbackExecuted = false;

        $region = $builder
            ->setStates('idle', 'active')
            ->markInitial('idle')
            ->addBuildStep(new \Noem\State\Feature\Transitions\AddTransition('idle', 'active', fn(object $trigger): bool => true))
            ->onEnter('active', function (object $trigger) use (&$resolverCalled, &$callbackExecuted) {
                $callbackExecuted = true;
                // Access the resolver property - this should trigger the resolver
                $value = $this->get('computed');
            })
            ->build([
                'loader' => [
                    'array' => [
                        'context' => [
                            'resolvers' => [
                                [
                                    'name' => 'computed',
                                    'run' => function () use (&$resolverCalled) {
                                        $resolverCalled = true;
                                        yield;
                                        return 'resolved-value';
                                    },
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

        $this->assertFalse($resolverCalled, 'Resolver should not be called before access');

        // Trigger to enter active state
        $region->trigger(new \stdClass());

        $this->assertTrue($callbackExecuted, 'onEnter callback should have been executed');

        // After multiple ticks, resolver should have been called
        for ($i = 0; $i < 5; $i++) {
            $region->trigger(new \stdClass());
        }

        $this->assertTrue($resolverCalled, 'Resolver should be called when property is accessed');
    }
}
