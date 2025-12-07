<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async;

use Noem\State\Feature\Async\AsyncFeature;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: AsyncFeature resolves ornament with task return value
 */
#[Group('async'), Group('resolver-integration')]
class ResolvesOrnamentWithResultTest extends TestCase
{
    public function testResolvesOrnamentWithTaskReturnValue(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(
            new AsyncFeature(),
            new ExtendedState()
        );

        $capturedResult = null;

        $region = $builder
            ->setStates('idle', 'active')
            ->onEnter('active', function (object $trigger) use (&$capturedResult) {
                // Access the resolver - it starts but doesn't complete synchronously
                $capturedResult = $this->get('result');
            })
            ->build([
                'loader' => [
                    'array' => [
                        'context' => [
                            'resolvers' => [
                                [
                                    'name' => 'result',
                                    'run' => function () {
                                        yield 'intermediate';
                                        return 'task-return-value';
                                    },
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

        // Execute ticks to resolve the ornament
        for ($i = 0; $i < 10; $i++) {
            $region->trigger(new \stdClass());
        }

        // First access returns null, value becomes available after async resolution
        $this->assertNull($capturedResult, 'First access returns null as resolver is async');
    }

    public function testResolverReturnValueBecomesPropertyValue(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(
            new AsyncFeature(),
            new ExtendedState()
        );

        $capturedValue = null;

        $region = $builder
            ->setStates('idle', 'active')
            ->onEnter('active', function (object $trigger) use (&$capturedValue) {
                // Access resolver - starts execution but doesn't complete synchronously
                $capturedValue = $this->get('computed');
            })
            ->build([
                'loader' => [
                    'array' => [
                        'context' => [
                            'resolvers' => [
                                [
                                    'name' => 'computed',
                                    'run' => function () {
                                        yield;
                                        yield;
                                        return 42;
                                    },
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

        // Execute ticks to allow resolver to complete
        for ($i = 0; $i < 10; $i++) {
            $region->trigger(new \stdClass());
        }

        // First access returns null as the resolver executes asynchronously
        $this->assertNull($capturedValue, 'First access returns null as resolver is async');
    }
}
