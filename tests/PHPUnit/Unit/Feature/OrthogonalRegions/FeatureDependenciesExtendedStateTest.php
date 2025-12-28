<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\OrthogonalRegions;

use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\OrthogonalRegions\OrthogonalRegions;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: OrthogonalRegions requires ExtendedState for context sharing
 */
#[Group('orthogonal-regions')]
#[Group('feature-dependencies')]
class FeatureDependenciesExtendedStateTest extends TestCase
{
    public function testOrthogonalRegionsWorksWithoutExtendedState(): void
    {
        // OrthogonalRegions can work without ExtendedState, but no context sharing
        $child = (new RegionBuilder())
            ->setStates('child')
            ->markInitial('child');

        $region = (new OrthogonalRegions(new RegionBuilder(), [$child]))
            ->setStates('parent')
            ->markInitial('parent')
            ->build();

        $this->assertNotNull($region);
        $this->assertFalse(property_exists($region, 'context'));
    }

    public function testOrthogonalRegionsWithExtendedStateEnablesContextSharing(): void
    {
        $contextAvailable = false;

        $child = (new RegionBuilder())
            ->setStates('child')
            ->markInitial('child')
            ->onEnter('child', function (object $t) use (&$contextAvailable) {
                $contextAvailable = method_exists($this, 'get') && method_exists($this, 'set');
            });

        $region = (new OrthogonalRegions(
            new ExtendedState(new RegionBuilder()),
            [$child]
        ))
            ->setStates('parent')
            ->markInitial('parent')
            ->build();

        $region->init();

        $this->assertTrue($contextAvailable);
        $this->assertTrue(property_exists($region, 'context'));
    }

    public function testFeatureOrderMattersForContextSharing(): void
    {
        // ExtendedState must be in the feature chain for context to work
        $child = (new RegionBuilder())
            ->setStates('child')
            ->markInitial('child');

        // Option 1: ExtendedState wraps OrthogonalRegions
        $region1 = (new ExtendedState(
            new OrthogonalRegions(new RegionBuilder(), [$child])
        ))
            ->setStates('parent1')
            ->markInitial('parent1')
            ->build();

        // Option 2: OrthogonalRegions wraps ExtendedState
        $region2 = (new OrthogonalRegions(
            new ExtendedState(new RegionBuilder()),
            [$child]
        ))
            ->setStates('parent2')
            ->markInitial('parent2')
            ->build();

        $this->assertTrue(property_exists($region1, 'context'));
        $this->assertTrue(property_exists($region2, 'context'));
    }

    public function testWithoutExtendedStateNoContextMethods(): void
    {
        $hasGetMethod = null;
        $hasSetMethod = null;

        $region = (new OrthogonalRegions(new RegionBuilder()))
            ->setStates('parent')
            ->markInitial('parent')
            ->onEnter('parent', function (object $t) use (&$hasGetMethod, &$hasSetMethod) {
                $hasGetMethod = method_exists($this, 'get');
                $hasSetMethod = method_exists($this, 'set');
            })
            ->build();

        $region->init();

        $this->assertFalse($hasGetMethod, 'get() should not exist without ExtendedState');
        $this->assertFalse($hasSetMethod, 'set() should not exist without ExtendedState');
    }

    public function testExtendedStateRequiredForBidirectionalCommunication(): void
    {
        $childCanRead = false;
        $parentCanWrite = false;

        $child = (new RegionBuilder())
            ->setStates('child')
            ->markInitial('child')
            ->onEnter('child', function (object $t) use (&$childCanRead) {
                $childCanRead = method_exists($this, 'get');
            });

        $region = (new OrthogonalRegions(
            new ExtendedState(new RegionBuilder()),
            [$child]
        ))
            ->setStates('parent')
            ->markInitial('parent')
            ->onEnter('parent', function (object $t) use (&$parentCanWrite) {
                $parentCanWrite = method_exists($this, 'set');
                $this->set('test', 'value');
            })
            ->build();

        $region->init();

        $this->assertTrue($parentCanWrite, 'Parent should be able to write with ExtendedState');
        $this->assertTrue($childCanRead, 'Child should be able to read with ExtendedState');
    }

    public function testSummonedRegionsAlsoRequireExtendedStateForContext(): void
    {
        $summonedHasContext = false;

        $childBuilder = (new RegionBuilder())
            ->setStates('child')
            ->markInitial('child')
            ->onEnter('child', function (object $t) use (&$summonedHasContext) {
                $summonedHasContext = method_exists($this, 'get');
            });

        // With ExtendedState
        $regionWithContext = (new OrthogonalRegions(new ExtendedState(new RegionBuilder())))
            ->setStates('parent')
            ->markInitial('parent')
            ->onEnter('parent', function (object $t) use ($childBuilder) {
                $this->summon($childBuilder)->run();
            })
            ->build();

        $regionWithContext->init();

        $this->assertTrue($summonedHasContext, 'Summoned regions need ExtendedState for context');
    }

    public function testExtendedStateEnablesStatefulOrthogonalCommunication(): void
    {
        $communicationWorks = false;

        $child = (new RegionBuilder())
            ->setStates('child')
            ->markInitial('child')
            ->onEnter('child', function (object $t) use (&$communicationWorks) {
                $message = $this->get('message');
                if ($message === 'hello_from_parent') {
                    $communicationWorks = true;
                }
            });

        $region = (new OrthogonalRegions(
            new ExtendedState(new RegionBuilder()),
            [$child]
        ))
            ->setStates('parent')
            ->markInitial('parent')
            ->onEnter('parent', function (object $t) {
                $this->set('message', 'hello_from_parent');
            })
            ->build();

        $region->init();

        $this->assertTrue($communicationWorks);
    }
}
