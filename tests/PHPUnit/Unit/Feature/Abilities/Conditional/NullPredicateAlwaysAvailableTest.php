<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Abilities\Conditional;

use Noem\State\Feature\Abilities\AbilitiesFeature;
use Noem\State\Feature\Abilities\AbilityDefinition;
use Noem\State\Feature\Abilities\AbilityRegistry;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\Message\MessageFeature;
use Noem\State\RegionBuilder;
use Noem\State\Test\Helpers\RegionBuilderTestCase;

/**
 * @covers \Noem\State\Feature\Abilities\InvokeAbility
 */
final class NullPredicateAlwaysAvailableTest extends RegionBuilderTestCase
{
    public function testAbilityWithoutPredicateIsAlwaysAvailable(): void
    {
        $invoked = false;

        $handler = function (array $params) use (&$invoked) {
            $invoked = true;
            return ['result' => 'always-available'];
        };

        $region = (new RegionBuilder())
            ->enableFeatures(
                new ExtendedState(),
                new MessageFeature(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use ($handler, &$invoked) {
                $registry = $this->get('__chainMail')->get(AbilityRegistry::class);

                $registry->register(new AbilityDefinition(
                    name: 'unconditional',
                    description: 'Always available',
                    parameterSchema: [],
                    responseSchema: [],
                    handler: $handler,
                    predicate: null // Explicitly null
                ));

                $this->abilities('unconditional');
            })
            ->build();

        $this->assertTrue(
            $invoked,
            'Ability with null predicate must be always available'
        );
    }
}
