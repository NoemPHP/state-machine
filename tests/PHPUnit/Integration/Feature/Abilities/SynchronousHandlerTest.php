<?php

declare(strict_types=1);

namespace Noem\State\Test\Integration\Feature\Abilities;

use Noem\State\Feature\Abilities\AbilitiesFeature;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\Message\MessageFeature;
use Noem\State\Test\Integration\RegionBuilderTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Acceptance Criterion: Ability handler executes during region.trigger() call
 *
 * Intent: Handler runs synchronously as part of dispatch pipeline,
 *         enabling immediate results
 *
 * @see specs/features/abilities.yaml - synchronous-behavior
 */
#[Group('feature')]
#[Group('abilities')]
#[Group('integration')]
#[Group('synchronous')]
class SynchronousHandlerTest extends RegionBuilderTestCase
{
    #[Test]
    public function handlerExecutesDuringTriggerCall(): void
    {
        // Given: Track execution timeline
        $executionLog = [];

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$executionLog) {
                $executionLog[] = 'before-register';

                // Register ability
                $this->abilities()->register('sync-test', [
                    'name' => 'sync-test',
                    'description' => 'Test synchronous execution',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => function () use (&$executionLog) {
                        $executionLog[] = 'handler-executing';
                        return ['result' => 'done'];
                    }
                ]);

                $executionLog[] = 'before-invoke';

                // Invoke ability
                $this->abilities('sync-test')
                    ->then(function () use (&$executionLog) {
                        $executionLog[] = 'response-received';
                    });

                $executionLog[] = 'after-invoke';
            })
            ->build();

        // When: Trigger region
        $executionLog[] = 'before-trigger';
        $region->trigger((object)[]);
        $executionLog[] = 'after-trigger';

        // Then: Handler should execute DURING the trigger() call, not after
        $this->assertContains('handler-executing', $executionLog);

        // Handler should execute between invoke and after-invoke
        $beforeInvokeIndex = array_search('before-invoke', $executionLog);
        $handlerIndex = array_search('handler-executing', $executionLog);
        $afterInvokeIndex = array_search('after-invoke', $executionLog);

        $this->assertLessThan(
            $afterInvokeIndex,
            $handlerIndex,
            'Handler should execute before abilities() call returns (synchronous execution)'
        );

        $this->assertGreaterThan(
            $beforeInvokeIndex,
            $handlerIndex,
            'Handler should execute after abilities() call starts'
        );
    }

    #[Test]
    public function handlerExecutionIsNotDeferred(): void
    {
        // Given: Track whether handler executes immediately
        $handlerCalled = false;
        $calledDuringTrigger = false;

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$handlerCalled, &$calledDuringTrigger) {
                // Register ability
                $this->abilities()->register('immediate', [
                    'name' => 'immediate',
                    'description' => 'Test immediate execution',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => function () use (&$handlerCalled) {
                        $handlerCalled = true;
                        return [];
                    }
                ]);

                // Invoke ability
                $this->abilities('immediate');

                // Check if handler was called synchronously
                $calledDuringTrigger = $handlerCalled;
            })
            ->build();

        // When: Trigger region
        $region->trigger((object)[]);

        // Then: Handler should have been called during the trigger, not deferred
        $this->assertTrue(
            $handlerCalled,
            'Handler should be called'
        );

        $this->assertTrue(
            $calledDuringTrigger,
            'Handler should execute synchronously during trigger call, not deferred to later'
        );
    }
}
