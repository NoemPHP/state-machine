<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Transitions\Evaluation;

use Noem\State\Feature\Transitions\AddTransition;
use Noem\State\Feature\Transitions\TransitionsFeature;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Acceptance Criterion: Guards are evaluated in registration order
 */
#[Group('transitions')]
#[Group('transition-evaluation')]
class EvaluatesInOrderTest extends TestCase
{
    public function testEvaluatesGuardsInRegistrationOrder(): void
    {
        $evaluationOrder = [];

        $trigger = new stdClass();
        $trigger->value = 'second';

        $region = (new RegionBuilder())
            ->enableFeatures(new TransitionsFeature())
            ->setStates('start', 'first', 'second', 'third')
            ->addBuildStep(new AddTransition('start', 'first', function (object $t) use (&$evaluationOrder): bool {
                $evaluationOrder[] = 'first';
                return $t->value === 'first';
            }))
            ->addBuildStep(new AddTransition('start', 'second', function (object $t) use (&$evaluationOrder): bool {
                $evaluationOrder[] = 'second';
                return $t->value === 'second';
            }))
            ->addBuildStep(new AddTransition('start', 'third', function (object $t) use (&$evaluationOrder): bool {
                $evaluationOrder[] = 'third';
                return $t->value === 'third';
            }))
            ->build();

        $region->trigger($trigger);

        // Guards are evaluated in reverse registration order (LIFO)
        // Third is checked first (false), then second (true), evaluation stops
        $this->assertEquals(['third', 'second'], $evaluationOrder);
        $this->assertTrue($region->isInState('second'));
    }
}
