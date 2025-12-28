<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Abilities\Conditional;

use Noem\State\Feature\Abilities\AbilitiesFeature;
use Noem\State\Feature\Abilities\AbilityDefinition;
use Noem\State\Feature\Abilities\AbilityRegistry;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\Message\MessageFeature;
use Noem\State\Region;
use Noem\State\RegionBuilder;
use Noem\State\Test\Helpers\RegionBuilderTestCase;

/**
 * @covers \Noem\State\Feature\Abilities\AbilitiesFeature
 */
final class HiddenExcludedFromEnumerateTest extends RegionBuilderTestCase
{
    public function testHiddenAbilitiesExcludedFromEnumeration(): void
    {
        $enumeratedAbilities = [];

        $predicate = fn(Region $region) => false;
        $handler = fn(array $params) => ['result' => 'hidden'];

        $region = (new RegionBuilder())
            ->enableFeatures(
                new ExtendedState(),
                new MessageFeature(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use ($predicate, $handler, &$enumeratedAbilities) {
                $registry = $this->get('__chainMail')->get(AbilityRegistry::class);

                $registry->register(new AbilityDefinition(
                    name: 'hidden-ability',
                    description: 'Should not appear',
                    parameterSchema: [],
                    responseSchema: [],
                    handler: $handler,
                    predicate: $predicate
                ));

                $this->abilities('enumerate-abilities')
                    ->then(function ($response) use (&$enumeratedAbilities) {
                        $enumeratedAbilities = array_column(
                            $response->parameters['abilities'],
                            'name'
                        );
                    });
            })
            ->build();

        $this->assertNotContains(
            'hidden-ability',
            $enumeratedAbilities,
            'Hidden abilities must be excluded from enumerate-abilities response'
        );
    }
}
