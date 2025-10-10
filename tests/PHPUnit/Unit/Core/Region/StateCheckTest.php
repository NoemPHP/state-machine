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
 * Acceptance Criterion: A region can check if it is in a specific state
 */
#[Group('region')]
#[Group('state-management')]
class StateCheckTest extends TestCase
{
    public function testIsInStateReturnsTrueForCurrentState(): void
    {
        $region = $this->createRegion('active', 'done');

        $this->assertTrue($region->isInState('active'));
    }

    public function testIsInStateReturnsFalseForDifferentState(): void
    {
        $region = $this->createRegion('active', 'done');

        $this->assertFalse($region->isInState('inactive'));
        $this->assertFalse($region->isInState('done'));
    }

    public function testIsInStateIsCaseSensitive(): void
    {
        $region = $this->createRegion('Active', 'Final');

        $this->assertTrue($region->isInState('Active'));
        $this->assertFalse($region->isInState('active'));
        $this->assertFalse($region->isInState('ACTIVE'));
    }

    public function testIsInStateHandlesEmptyString(): void
    {
        $region = $this->createRegion('', 'final');

        $this->assertTrue($region->isInState(''));
        $this->assertFalse($region->isInState('any'));
    }

    private function createRegion(string $initial, string $final): Region
    {
        $events = \Mockery::mock(Events::class);
        $actionChain = \Mockery::mock(DispatchAction::class);
        $actionChain->shouldReceive('link');

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
