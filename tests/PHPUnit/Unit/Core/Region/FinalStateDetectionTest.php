<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Core\Region;

use Noem\State\Chains\DispatchAction;
use Noem\State\Chains\DoTransition;
use Noem\State\Chains\Path;
use Noem\State\Events;
use Noem\State\Region;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: A region can detect when it has reached the final state
 */
#[Group('region')]
#[Group('state-management')]
class FinalStateDetectionTest extends TestCase
{
    public function testIsFinalReturnsFalseWhenNotInFinalState(): void
    {
        $region = $this->createRegion('initial', 'final');

        $this->assertFalse($region->isFinal());
    }

    public function testIsFinalReturnsTrueWhenInFinalState(): void
    {
        $region = $this->createRegion('final', 'final');

        $this->assertTrue($region->isFinal());
    }

    public function testIsFinalChecksExactMatch(): void
    {
        $region = $this->createRegion('almost_final', 'final');

        $this->assertFalse($region->isFinal());
    }

    public function testIsFinalIsCaseSensitive(): void
    {
        $region1 = $this->createRegion('Final', 'final');
        $region2 = $this->createRegion('final', 'Final');

        $this->assertFalse($region1->isFinal());
        $this->assertFalse($region2->isFinal());
    }

    private function createRegion(string $initial, string $final): Region
    {
        $events = \Mockery::mock(Events::class);
        $actionChain = \Mockery::mock(DispatchAction::class);
        $transitionChain = \Mockery::mock(DoTransition::class);
        $pathChain = \Mockery::mock(Path::class);

        $actionChain->shouldReceive('call')
            ->andReturn($initial)
            ->byDefault();

        return new Region(
            $events,
            $initial,
            $final,
            $actionChain,
            $transitionChain,
            $pathChain
        );
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}
