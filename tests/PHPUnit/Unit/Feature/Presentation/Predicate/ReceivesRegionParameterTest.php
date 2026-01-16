<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Presentation\Predicate;

use Noem\State\Feature\Presentation\RegionPresentation;
use Noem\State\Region;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: Predicate receives Region instance as parameter
 * Intent: Provides region context for predicate evaluation, enabling currentState checks
 * Criticality: contract
 */
final class ReceivesRegionParameterTest extends TestCase
{
    public function testPredicateReceivesRegionParameter(): void
    {
        $receivedParameter = null;

        $predicate = function (Region $region) use (&$receivedParameter): bool {
            $receivedParameter = $region;
            return true;
        };

        $presentation = new RegionPresentation(
            key: 'testKey',
            label: 'Test Label',
            intent: 'Test Intent',
            predicate: $predicate
        );

        $mockRegion = $this->createMock(Region::class);
        ($presentation->predicate)($mockRegion);

        // Verify the predicate received the Region instance
        $this->assertSame($mockRegion, $receivedParameter);
    }

    public function testPredicateCanAccessRegionState(): void
    {
        $predicate = function (Region $region): bool {
            // Predicate should be able to access region properties
            // like currentState
            return $region->currentState === 'active';
        };

        $presentation = new RegionPresentation(
            key: 'testKey',
            label: 'Test Label',
            intent: 'Test Intent',
            predicate: $predicate
        );

        $mockRegion = $this->createMock(Region::class);
        $mockRegion->currentState = 'active';

        $result = ($presentation->predicate)($mockRegion);

        $this->assertTrue($result);
    }
}
