<?php

declare(strict_types=1);

namespace Tests\Integration\Feature\Abilities;

use Noem\State\Feature\Abilities\AbilitiesFeature;
use Noem\State\Feature\Abilities\AbilityDefinition;
use Noem\State\Feature\Abilities\AbilityRegistry;
use Noem\State\Feature\Abilities\BuildStep\RegisterAbility;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\Message\MessageFeature;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Integration test validating basic ability invocation workflow
 */
final class BasicAbilityInvocationTest extends TestCase
{
    public function testAbilityInvocationWithResponse(): void
    {
        $responseReceived = null;

        $region = (new RegionBuilder())
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AbilitiesFeature(),
            )
            ->setStates('idle')
            ->addBuildStep(new RegisterAbility(
                name: 'test-ability',
                handler: fn($params) => ['result' => 'success', 'input' => $params],
                description: 'Test ability for integration testing',
                parameterSchema: [
                    'type' => 'object',
                    'properties' => [
                        'value' => ['type' => 'string'],
                    ],
                    'required' => ['value'],
                ],
            ))
            ->onEnter('idle', function (object $trigger) use (&$responseReceived) {
                $message = $this->abilities('test-ability', ['value' => 'test-input']);

                $message->then(function ($response) use (&$responseReceived) {
                    $responseReceived = $response->parameters;
                });
            })
            ->build();

        $region->trigger((object)[]);

        $this->assertNotNull($responseReceived, 'Response should be received');
        $this->assertEquals('success', $responseReceived['result']);
        $this->assertEquals(['value' => 'test-input'], $responseReceived['input']);
    }

    public function testEnumerateAbilities(): void
    {
        $abilities = null;

        $region = (new RegionBuilder())
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AbilitiesFeature(),
            )
            ->setStates('idle')
            ->addBuildStep(new RegisterAbility(
                name: 'custom-ability',
                handler: fn() => ['data' => 'value'],
                description: 'Custom test ability',
            ))
            ->onEnter('idle', function (object $trigger) use (&$abilities) {
                $message = $this->abilities('enumerate-abilities', []);

                $message->then(function ($response) use (&$abilities) {
                    $abilities = $response->parameters['abilities'];
                });
            })
            ->build();

        $region->trigger((object)[]);

        $this->assertIsArray($abilities);
        $this->assertCount(2, $abilities); // enumerate-abilities + custom-ability

        $names = array_column($abilities, 'name');
        $this->assertContains('enumerate-abilities', $names);
        $this->assertContains('custom-ability', $names);
    }

    public function testSchemaValidationRejectsInvalidParameters(): void
    {
        $this->expectException(\Noem\State\Feature\Abilities\SchemaValidationException::class);

        $region = (new RegionBuilder())
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AbilitiesFeature(),
            )
            ->setStates('idle')
            ->addBuildStep(new RegisterAbility(
                name: 'strict-ability',
                handler: fn($params) => $params,
                parameterSchema: [
                    'type' => 'object',
                    'properties' => [
                        'count' => ['type' => 'number'],
                    ],
                    'required' => ['count'],
                ],
            ))
            ->onEnter('idle', function (object $trigger) {
                // Missing required 'count' parameter - should throw
                $this->abilities('strict-ability', ['name' => 'invalid']);
            })
            ->build();

        $region->trigger((object)[]);
    }

    public function testAbilityWithPredicate(): void
    {
        $region = (new RegionBuilder())
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AbilitiesFeature(),
            )
            ->setStates('locked', 'unlocked')
            ->addBuildStep(new RegisterAbility(
                name: 'protected-ability',
                handler: fn() => ['success' => true],
                predicate: fn($r) => $r->currentState() === 'unlocked',
                description: 'Only available when unlocked',
            ))
            ->onEnter('locked', function (object $trigger) {
                // Should throw when predicate returns false
                $this->abilities('protected-ability', []);
            })
            ->build();

        // Expect exception when invoking ability with false predicate
        $this->expectException(\Noem\State\Feature\Abilities\AbilityNotFoundException::class);
        $region->trigger((object)[]);
    }
}
