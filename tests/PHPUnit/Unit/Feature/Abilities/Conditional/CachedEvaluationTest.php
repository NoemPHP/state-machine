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
final class CachedEvaluationTest extends RegionBuilderTestCase
{
    public function testPredicateResultCachedDuringSingleInvocation(): void
    {
        $evaluationCount = 0;

        $predicate = function (Region $region) use (&$evaluationCount) {
            $evaluationCount++;
            return true;
        };

        $handler = fn(array $params) => ['result' => 'test'];

        $region = (new RegionBuilder())
            ->enableFeatures(
                new ExtendedState(),
                new MessageFeature(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use ($predicate, $handler, &$evaluationCount) {
                $registry = $this->get('__chainMail')->get(AbilityRegistry::class);

                $registry->register(new AbilityDefinition(
                    name: 'test-ability',
                    description: 'Test',
                    parameterSchema: [],
                    responseSchema: [],
                    handler: $handler,
                    predicate: $predicate
                ));

                // Single invocation - predicate should only evaluate once
                // even if middleware chain processes it multiple times
                $this->abilities('test-ability');
            })
            ->build();

        $this->assertEquals(
            1,
            $evaluationCount,
            'Predicate result should be cached during single invocation chain to prevent redundant evaluation'
        );
    }
}
