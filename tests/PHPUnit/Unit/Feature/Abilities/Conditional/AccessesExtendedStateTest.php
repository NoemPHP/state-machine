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
 * @covers \Noem\State\Feature\Abilities\InvokeAbility
 */
final class AccessesExtendedStateTest extends RegionBuilderTestCase
{
    public function testPredicateCanAccessExtendedStateContext(): void
    {
        $contextValue = null;

        $predicate = function (Region $region) use (&$contextValue) {
            // Access ExtendedState context through Meta chain
            $meta = $region->chainMail->get(\Noem\State\Chains\Meta::class);
            $contextType = \Noem\State\Feature\ExtendedState\ContextMetaType::get();
            $context = $meta->call(new \Noem\State\Chains\Params\Meta($region, $contextType));

            $contextValue = $context['userRole'] ?? null;

            return $contextValue === 'admin';
        };

        $handler = fn(array $params) => ['result' => 'admin-only'];

        $region = (new RegionBuilder())
            ->enableFeatures(
                new ExtendedState(),
                new MessageFeature(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use ($predicate, $handler) {
                $this->set('userRole', 'admin');

                $registry = $this->get('__chainMail')->get(AbilityRegistry::class);

                $registry->register(new AbilityDefinition(
                    name: 'admin-ability',
                    description: 'Admin only',
                    parameterSchema: [],
                    responseSchema: [],
                    handler: $handler,
                    predicate: $predicate
                ));

                $this->abilities('admin-ability');
            })
            ->build();

        $this->assertEquals(
            'admin',
            $contextValue,
            'Predicate must be able to access ExtendedState context for conditional logic'
        );
    }
}
