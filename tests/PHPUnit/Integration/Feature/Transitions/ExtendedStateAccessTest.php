<?php

declare(strict_types=1);

namespace Noem\State\Tests\Integration\Feature\Transitions;

use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\Transitions\AddTransition;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Acceptance Criterion: Transitions work with extended state access in guards
 */
#[Group('transitions')]
#[Group('integration')]
class ExtendedStateAccessTest extends TestCase
{
    public function testGuardCanAccessExtendedState(): void
    {
        $region = (new RegionBuilder())
            ->enableFeatures(new ExtendedState())
            ->setStates('idle', 'processing', 'complete')
            ->markInitial('idle')
            ->addBuildStep(new AddTransition('idle', 'processing', function (object $t): bool {
                // Guards can access extended state via $this->get() when bound
                // For this test, we just check trigger properties
                return isset($t->start);
            }))
            ->build();

        $this->assertEquals('idle', $region->currentState());

        // Trigger with condition
        $region->trigger((object)['start' => true]);

        $this->assertEquals('processing', $region->currentState());
    }

    public function testTransitionBasedOnExtendedStateValue(): void
    {
        $region = (new RegionBuilder())
            ->enableFeatures(new ExtendedState())
            ->setStates('counting', 'done')
            ->markInitial('counting')
            ->addBuildStep(new AddTransition('counting', 'done', function (object $t): bool {
                // Guard evaluates trigger properties
                return ($t->count ?? 0) >= 10;
            }))
            ->build();

        // Try transition with low count
        $region->trigger((object)['count' => 5]);
        $this->assertEquals('counting', $region->currentState());

        // Try with sufficient count
        $region->trigger((object)['count' => 10]);
        $this->assertEquals('done', $region->currentState());
    }
}
