<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Presentation\Predicate;

use Noem\State\Feature\Presentation\RegionPresentation;
use Noem\State\Region;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: Predicates evaluated lazily at enumeration time
 * Intent: Ensures predicate evaluation reflects current state, not registration-time state
 * Criticality: contract
 */
final class LazyEvaluationTest extends TestCase
{
    public function testPredicateNotEvaluatedAtRegistration(): void
    {
        $evaluationCount = 0;

        $predicate = function (Region $region) use (&$evaluationCount): bool {
            $evaluationCount++;
            return true;
        };

        // Create presentation - predicate should NOT be evaluated yet
        $presentation = new RegionPresentation(
            key: 'testKey',
            label: 'Test Label',
            intent: 'Test Intent',
            predicate: $predicate
        );

        // Predicate should not have been called during construction
        $this->assertSame(0, $evaluationCount);

        // Predicate is stored for later evaluation
        $this->assertNotNull($presentation->predicate);
    }

    public function testPredicateEvaluatedOnDemand(): void
    {
        $evaluationCount = 0;

        $predicate = function (Region $region) use (&$evaluationCount): bool {
            $evaluationCount++;
            return true;
        };

        $presentation = new RegionPresentation(
            key: 'testKey',
            label: 'Test Label',
            intent: 'Test Intent',
            predicate: $predicate
        );

        $mockRegion = $this->createMock(Region::class);

        // Predicate not evaluated yet
        $this->assertSame(0, $evaluationCount);

        // Call predicate explicitly
        ($presentation->predicate)($mockRegion);

        // Now it should be evaluated
        $this->assertSame(1, $evaluationCount);
    }

    public function testPredicateReflectsCurrentState(): void
    {
        $stateAtEvaluation = null;

        $predicate = function (Region $region) use (&$stateAtEvaluation): bool {
            $stateAtEvaluation = $region->currentState;
            return true;
        };

        $presentation = new RegionPresentation(
            key: 'testKey',
            label: 'Test Label',
            intent: 'Test Intent',
            predicate: $predicate
        );

        // No state captured yet
        $this->assertNull($stateAtEvaluation);

        // Mock region with initial state
        $mockRegion = $this->createMock(Region::class);
        $mockRegion->currentState = 'initial';

        ($presentation->predicate)($mockRegion);

        // Should capture current state at evaluation time
        $this->assertSame('initial', $stateAtEvaluation);

        // Change state
        $mockRegion->currentState = 'processing';

        ($presentation->predicate)($mockRegion);

        // Should reflect new state
        $this->assertSame('processing', $stateAtEvaluation);
    }
}
