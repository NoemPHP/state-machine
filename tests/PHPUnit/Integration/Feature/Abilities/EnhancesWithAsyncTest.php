<?php

declare(strict_types=1);

namespace Noem\State\Test\Integration\Feature\Abilities;

use Noem\State\Feature\Abilities\AbilitiesFeature;
use Noem\State\Feature\Async\AsyncFeature;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\Message\MessageFeature;
use Noem\State\Test\Integration\RegionBuilderTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Acceptance Criterion: AsyncFeature enhances abilities without breaking synchronous behavior
 *
 * Intent: AsyncFeature is an OPTIONAL enhancement that adds async capabilities to abilities,
 *         but the same API works in both modes (progressive enhancement pattern).
 *         Synchronous behavior remains available and functional when AsyncFeature is enabled.
 *
 * @see specs/features/abilities.yaml - async-enhancement
 */
#[Group('feature')]
#[Group('abilities')]
#[Group('integration')]
#[Group('async')]
class EnhancesWithAsyncTest extends RegionBuilderTestCase
{
    #[Test]
    public function asyncFeatureEnhancesWithoutBreakingSynchronous(): void
    {
        // Given: A region with AsyncFeature + AbilitiesFeature
        // ExtendedState is required before AsyncFeature per architectural rules
        $syncResponseReceived = false;

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AsyncFeature(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$syncResponseReceived) {
                // Register a simple synchronous ability
                $this->abilities()->register('sync-test', [
                    'name' => 'sync-test',
                    'description' => 'Synchronous test ability',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => fn() => ['result' => 'sync']
                ]);

                // Invoke without yielding - should still work synchronously
                $this->abilities('sync-test')
                    ->then(function ($response) use (&$syncResponseReceived) {
                        $syncResponseReceived = true;
                    });
            })
            ->build();

        // When: Trigger region
        $region->trigger((object)[]);

        // Then: Synchronous behavior still works even with AsyncFeature enabled
        $this->assertTrue(
            $syncResponseReceived,
            'AsyncFeature should not break synchronous ability invocations'
        );
    }

    #[Test]
    public function sameApiWorksInBothModes(): void
    {
        // Given: Two regions - one with AsyncFeature, one without
        $syncResult = null;
        $asyncResult = null;

        // Synchronous region
        $syncRegion = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$syncResult) {
                $this->abilities()->register('test', [
                    'name' => 'test',
                    'description' => 'Test',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => fn() => ['mode' => 'sync']
                ]);

                $this->abilities('test')
                    ->then(function ($response) use (&$syncResult) {
                        $syncResult = 'received';
                    });
            })
            ->build();

        // Async region (requires ExtendedState before AsyncFeature)
        $builder2 = new \Noem\State\RegionBuilder();
        $asyncRegion = $builder2
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AsyncFeature(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$asyncResult) {
                $this->abilities()->register('test', [
                    'name' => 'test',
                    'description' => 'Test',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => fn() => ['mode' => 'async']
                ]);

                // EXACT SAME API CALL
                $this->abilities('test')
                    ->then(function ($response) use (&$asyncResult) {
                        $asyncResult = 'received';
                    });
            })
            ->build();

        // When: Trigger both regions
        $syncRegion->trigger((object)[]);
        $asyncRegion->trigger((object)[]);

        // Then: Same API works in both modes
        $this->assertSame('received', $syncResult, 'Sync mode should work');
        $this->assertSame('received', $asyncResult, 'Async mode should work with identical API');
    }

    #[Test]
    public function progressiveEnhancementPattern(): void
    {
        // Given: Region with AsyncFeature can handle both sync and async handlers
        $syncHandlerCalled = false;
        $asyncHandlerCalled = false;

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AsyncFeature(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$syncHandlerCalled, &$asyncHandlerCalled) {
                // Register synchronous ability handler
                $this->abilities()->register('sync-handler', [
                    'name' => 'sync-handler',
                    'description' => 'Synchronous handler',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => function () use (&$syncHandlerCalled) {
                        $syncHandlerCalled = true;
                        return ['type' => 'sync'];
                    }
                ]);

                // Register async generator handler
                $this->abilities()->register('async-handler', [
                    'name' => 'async-handler',
                    'description' => 'Async generator handler',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => function () use (&$asyncHandlerCalled) {
                        // This is a generator - progressive enhancement
                        $asyncHandlerCalled = true;
                        yield;
                        return ['type' => 'async'];
                    }
                ]);

                // Invoke both - AsyncFeature handles both gracefully
                $this->abilities('sync-handler');
                $this->abilities('async-handler');
            })
            ->build();

        // When: Trigger region
        $region->trigger((object)[]);

        // Then: Both handler types work with AsyncFeature enabled
        $this->assertTrue(
            $syncHandlerCalled,
            'Synchronous handlers should still work with AsyncFeature'
        );
        $this->assertTrue(
            $asyncHandlerCalled,
            'Async generator handlers should work with AsyncFeature'
        );
    }
}
