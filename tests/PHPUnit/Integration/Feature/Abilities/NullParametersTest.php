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
 * Acceptance Criterion: Abilities handle null parameters correctly
 *
 * Intent: Validates parameterless invocations and null parameter handling
 *
 * @see specs/features/abilities.yaml - integration (derived from spec analysis)
 */
#[Group('feature')]
#[Group('abilities')]
#[Group('integration')]
class NullParametersTest extends RegionBuilderTestCase
{
    #[Test]
    public function parameterlessInvocation(): void
    {
        // Given: Ability invoked without parameters
        $response = null;
        $receivedParams = null;

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$response, &$receivedParams) {
                // Register parameterless ability
                $this->abilities()->register('no-params', [
                    'name' => 'no-params',
                    'description' => 'No parameters required',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => function ($params) use (&$receivedParams) {
                        $receivedParams = $params;
                        return ['executed' => true];
                    }
                ]);

                // Invoke without parameters
                $this->abilities('no-params')
                    ->then(function ($data) use (&$response) {
                        $response = $data;
                    });
            })
            ->build();

        // When: Trigger region
        $region->trigger((object)[]);

        // Then: Should handle null parameters
        $this->assertNotNull($response);
        $this->assertTrue($response->parameters['executed']);
        $this->assertNull($receivedParams);
    }

    #[Test]
    public function explicitNullParameters(): void
    {
        // Given: Ability invoked with explicit null
        $receivedParams = 'not-set';

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$receivedParams) {
                // Register ability
                $this->abilities()->register('explicit-null', [
                    'name' => 'explicit-null',
                    'description' => 'Explicit null test',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => function ($params) use (&$receivedParams) {
                        $receivedParams = $params;
                        return [];
                    }
                ]);

                // Invoke with explicit null
                $this->abilities('explicit-null', null);
            })
            ->build();

        // When: Trigger region
        $region->trigger((object)[]);

        // Then: Handler should receive null
        $this->assertNull($receivedParams);
    }

    #[Test]
    public function emptySchemaAllowsNullParameters(): void
    {
        // Given: Ability with empty parameter schema
        $response = null;

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$response) {
                // Register ability with empty schema
                $this->abilities()->register('empty-schema', [
                    'name' => 'empty-schema',
                    'description' => 'Empty schema',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => fn($params) => ['ok' => true]
                ]);

                // Invoke without parameters
                $this->abilities('empty-schema')
                    ->then(function ($data) use (&$response) {
                        $response = $data;
                    });
            })
            ->build();

        // When: Trigger region
        $region->trigger((object)[]);

        // Then: Should succeed with null parameters
        $this->assertNotNull($response);
        $this->assertTrue($response->parameters['ok']);
    }
}
