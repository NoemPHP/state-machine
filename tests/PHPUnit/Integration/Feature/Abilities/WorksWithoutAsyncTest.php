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
 * Acceptance Criterion: Ability invocation works without AsyncFeature enabled
 *
 * Intent: Core abilities infrastructure operates with only MessageFeature dependency,
 *         maintaining simplicity
 *
 * @see specs/features/abilities.yaml - synchronous-behavior
 */
#[Group('feature')]
#[Group('abilities')]
#[Group('integration')]
#[Group('synchronous')]
class WorksWithoutAsyncTest extends RegionBuilderTestCase
{
    #[Test]
    public function abilitiesWorkWithoutAsyncFeature(): void
    {
        // Given: A region with ONLY MessageFeature and AbilitiesFeature (no AsyncFeature)
        $responseCaptured = false;

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AbilitiesFeature()
            )
            ->setStates('idle', 'processing')
            ->onEnter('idle', function (object $t) use (&$responseCaptured) {
                // Register a simple ability
                $this->abilities()->register('greet', [
                    'name' => 'greet',
                    'description' => 'Returns a greeting',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => fn($params) => ['message' => 'Hello, World!']
                ]);

                // Invoke the ability and register response handler
                $this->abilities('greet')
                    ->then(function ($response) use (&$responseCaptured) {
                        $responseCaptured = true;
                    });
            })
            ->build();

        // When: We trigger the region
        $region->trigger((object)[]);

        // Then: The ability should work without AsyncFeature
        // The response should be delivered synchronously
        $this->assertTrue(
            $responseCaptured,
            'Ability invocation should work without AsyncFeature - only MessageFeature required'
        );
    }

    #[Test]
    public function messageFeatureAloneIsSufficient(): void
    {
        // Given: Region with MessageFeature but explicitly NO AsyncFeature
        $handlerExecuted = false;
        $responseReceived = false;

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$handlerExecuted, &$responseReceived) {
                // Register ability with handler that sets flag
                $this->abilities()->register('test', [
                    'name' => 'test',
                    'description' => 'Test ability',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => function () use (&$handlerExecuted) {
                        $handlerExecuted = true;
                        return ['status' => 'ok'];
                    }
                ]);

                // Invoke and verify response
                $this->abilities('test')
                    ->then(function () use (&$responseReceived) {
                        $responseReceived = true;
                    });
            })
            ->build();

        // When: Trigger region
        $region->trigger((object)[]);

        // Then: Both handler execution and response delivery work
        $this->assertTrue($handlerExecuted, 'Handler should execute without AsyncFeature');
        $this->assertTrue($responseReceived, 'Response should be delivered without AsyncFeature');
    }
}
