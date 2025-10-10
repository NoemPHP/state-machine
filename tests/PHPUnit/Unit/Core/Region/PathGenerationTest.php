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
 * Acceptance Criterion: Region provides path through path chain
 */
#[Group('region')]
#[Group('path-generation')]
class PathGenerationTest extends TestCase
{
    public function testPathCallsPathChain(): void
    {
        $events = \Mockery::mock(Events::class);
        $actionChain = \Mockery::mock(DispatchAction::class);
        $actionChain->shouldReceive('link');

        $transitionChain = \Mockery::mock(DoTransition::class);
        $pathChain = \Mockery::mock(Path::class);

        $pathChain->shouldReceive('call')
            ->once()
            ->with(\Mockery::type(Region::class))
            ->andReturn('some/path');

        $region = new Region(
            $events,
            'initial',
            'final',
            $actionChain,
            $transitionChain,
            $pathChain
        );

        $result = $region->path();

        $this->assertEquals('some/path', $result);
    }

    public function testPathReturnsStringFromChain(): void
    {
        $events = \Mockery::mock(Events::class);
        $actionChain = \Mockery::mock(DispatchAction::class);
        $actionChain->shouldReceive('link');

        $transitionChain = \Mockery::mock(DoTransition::class);
        $pathChain = \Mockery::mock(Path::class);

        $pathChain->shouldReceive('call')
            ->andReturn('my/state/path');

        $region = new Region(
            $events,
            'initial',
            'final',
            $actionChain,
            $transitionChain,
            $pathChain
        );

        $path = $region->path();

        $this->assertIsString($path);
        $this->assertEquals('my/state/path', $path);
    }

    public function testPathPassesRegionToChain(): void
    {
        $events = \Mockery::mock(Events::class);
        $actionChain = \Mockery::mock(DispatchAction::class);
        $actionChain->shouldReceive('link');

        $transitionChain = \Mockery::mock(DoTransition::class);
        $pathChain = \Mockery::mock(Path::class);

        $capturedRegion = null;
        $pathChain->shouldReceive('call')
            ->andReturnUsing(function ($region) use (&$capturedRegion) {
                $capturedRegion = $region;
                return 'path';
            });

        $region = new Region(
            $events,
            'initial',
            'final',
            $actionChain,
            $transitionChain,
            $pathChain
        );

        $region->path();

        $this->assertSame($region, $capturedRegion);
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}
