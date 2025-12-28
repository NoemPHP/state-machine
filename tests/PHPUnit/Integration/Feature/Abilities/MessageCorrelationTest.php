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
 * Acceptance Criterion: Response correlation IDs match request IDs
 *
 * Intent: Validates correlation-based response delivery through MessageFeature
 *
 * @see specs/features/abilities.yaml - integration (derived from spec analysis)
 */
#[Group('feature')]
#[Group('abilities')]
#[Group('integration')]
#[Group('correlation')]
class MessageCorrelationTest extends RegionBuilderTestCase
{
    #[Test]
    public function responseCorrelationIdMatchesRequest(): void
    {
        // Given: Ability invocation capturing request and response
        $requestCorrelationId = null;
        $responseCorrelationId = null;

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$requestCorrelationId, &$responseCorrelationId) {
                // Register ability
                $this->abilities()->register('correlation-test', [
                    'name' => 'correlation-test',
                    'description' => 'Test correlation',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => fn() => ['data' => 'test']
                ]);

                // Invoke and capture correlation IDs
                $requestMessage = $this->abilities('correlation-test');
                $requestCorrelationId = $requestMessage->correlationId;

                $requestMessage->then(function ($responseMessage) use (&$responseCorrelationId) {
                    $responseCorrelationId = $responseMessage->correlationId ?? null;
                });
            })
            ->build();

        // When: Trigger region
        $region->trigger((object)[]);

        // Then: Correlation IDs should match
        $this->assertNotNull($requestCorrelationId);
        $this->assertNotNull($responseCorrelationId);
        $this->assertSame(
            $requestCorrelationId,
            $responseCorrelationId,
            'Response correlation ID must match request correlation ID'
        );
    }

    #[Test]
    public function multipleInvocationsHaveUniqueCorrelationIds(): void
    {
        // Given: Multiple ability invocations
        $correlationIds = [];

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$correlationIds) {
                // Register ability
                $this->abilities()->register('multi-invoke', [
                    'name' => 'multi-invoke',
                    'description' => 'Multiple invocations',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => fn() => []
                ]);

                // Invoke multiple times
                for ($i = 0; $i < 5; $i++) {
                    $message = $this->abilities('multi-invoke');
                    $correlationIds[] = $message->correlationId;
                }
            })
            ->build();

        // When: Trigger region
        $region->trigger((object)[]);

        // Then: Each invocation should have unique correlation ID
        $this->assertCount(5, $correlationIds);
        $uniqueIds = array_unique($correlationIds);
        $this->assertCount(
            5,
            $uniqueIds,
            'Each invocation should have a unique correlation ID'
        );
    }

    #[Test]
    public function correctResponseDeliveredToCorrectCallback(): void
    {
        // Given: Multiple concurrent ability invocations
        $responses = [];

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$responses) {
                // Register abilities with different responses
                $this->abilities()->register('ability-1', [
                    'name' => 'ability-1',
                    'description' => 'First ability',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => fn() => ['ability' => 1]
                ]);

                $this->abilities()->register('ability-2', [
                    'name' => 'ability-2',
                    'description' => 'Second ability',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => fn() => ['ability' => 2]
                ]);

                $this->abilities()->register('ability-3', [
                    'name' => 'ability-3',
                    'description' => 'Third ability',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => fn() => ['ability' => 3]
                ]);

                // Invoke all three
                $this->abilities('ability-1')
                    ->then(function ($response) use (&$responses) {
                        $responses['ability-1'] = $response;
                    });

                $this->abilities('ability-2')
                    ->then(function ($response) use (&$responses) {
                        $responses['ability-2'] = $response;
                    });

                $this->abilities('ability-3')
                    ->then(function ($response) use (&$responses) {
                        $responses['ability-3'] = $response;
                    });
            })
            ->build();

        // When: Trigger region
        $region->trigger((object)[]);

        // Then: Each callback receives correct response
        $this->assertSame(1, $responses['ability-1']->parameters['ability']);
        $this->assertSame(2, $responses['ability-2']->parameters['ability']);
        $this->assertSame(3, $responses['ability-3']->parameters['ability']);
    }
}
