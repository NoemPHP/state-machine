<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async;

use Noem\State\Chains\Params\Callback;
use Noem\State\Chains\PrepareInvokable;
use Noem\State\Feature\Async\AsyncFeature;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: AsyncFeature processes resolver callbacks through prepare and invoke chains
 */
#[Group('async'), Group('resolver-integration')]
class ProcessesResolverCallbacksTest extends TestCase
{
    public function testProcessesResolverCallbacks(): void
    {
        $prepareInvokableCalled = false;

        $builder = new RegionBuilder();

        // Get reference to the default PrepareInvokable before enabling features
        $prepareInvokable = $builder->chainMail->get(PrepareInvokable::class);

        // Add middleware to track when it's called
        $prepareInvokable->link(function (Callback $ctx, callable $next) use (&$prepareInvokableCalled): callable {
            $prepareInvokableCalled = true;
            return $next($ctx);
        });

        $builder->enableFeatures(
            new AsyncFeature(),
            new ExtendedState()
        );

        $valueFromResolver = null;

        $region = $builder
            ->setStates('active')
            ->onAction('active', function (object $t) use (&$valueFromResolver) {
                // Access the resolver to trigger its processing
                $valueFromResolver = $this->get('testValue');
            })
            ->build([
                'loader' => [
                    'array' => [
                        'context' => [
                            'resolvers' => [
                                [
                                    'name' => 'testValue',
                                    'run' => function () {
                                        yield;
                                        return 'computed-value';
                                    },
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

        // Trigger to enter active state and access resolver
        $region->trigger(new \stdClass());

        // Need to trigger a few more times to allow the resolver to complete
        for ($i = 0; $i < 5; $i++) {
            $region->trigger(new \stdClass());
        }

        // Verify that PrepareInvokable was called during resolver processing
        $this->assertTrue($prepareInvokableCalled, 'Resolver callback should be processed through PrepareInvokable chain');

        // Verify the resolver actually worked
        $this->assertSame('computed-value', $valueFromResolver);
    }
}
