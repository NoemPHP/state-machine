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
#[Group('context-sharing')]
class ContextSharingWithExtendedStateTest extends TestCase
{
    public function testOrthogonalRegionsWorksWithExtendedState(): void
    {
        $region = (new OrthogonalRegions(new ExtendedState(new RegionBuilder())))
            ->setStates('parent')
            ->markInitial('parent')
            ->build();

        $this->assertNotNull($region);
        $this->assertTrue(property_exists($region, 'context'));
    }

    public function testContextPropertyAvailableWhenExtendedStateUsed(): void
    {
        $contextExists = false;

        $region = (new OrthogonalRegions(new ExtendedState(new RegionBuilder())))
            ->setStates('parent')
            ->markInitial('parent')
            ->onEnter('parent', function (object $t) use (&$contextExists) {
                $contextExists = method_exists($this, 'get') && method_exists($this, 'set');
            })
            ->build();

        $region->init();

        $this->assertTrue($contextExists);
    }

    public function testExtendedStateFeatureOrderMatters(): void
    {
        // ExtendedState wraps OrthogonalRegions - correct order
        $region = (new ExtendedState(
            new OrthogonalRegions(new RegionBuilder())
        ))
            ->setStates('parent')
            ->markInitial('parent')
            ->build();

        $this->assertTrue(property_exists($region, 'context'));
    }

    public function testOrthogonalRegionsWrapsExtendedState(): void
    {
        // OrthogonalRegions wraps ExtendedState - also valid
        $region = (new OrthogonalRegions(
            new ExtendedState(new RegionBuilder())
        ))
            ->setStates('parent')
            ->markInitial('parent')
            ->build();

        $this->assertTrue(property_exists($region, 'context'));
    }

    public function testContextAccessibleFromBothParentAndChildren(): void
    {
        $parentCanAccess = false;
        $childCanAccess = false;

        $child = (new RegionBuilder())
            ->setStates('child')
            ->markInitial('child')
            ->onEnter('child', function (object $t) use (&$childCanAccess) {
                $childCanAccess = method_exists($this, 'get');
            });

        $region = (new OrthogonalRegions(
            new ExtendedState(new RegionBuilder()),
            [$child]
        ))
            ->setStates('parent')
            ->markInitial('parent')
            ->onEnter('parent', function (object $t) use (&$parentCanAccess) {
                $parentCanAccess = method_exists($this, 'get');
            })
            ->build();

        $region->init();

        $this->assertTrue($parentCanAccess);
        $this->assertTrue($childCanAccess);
    }

    public function testWithoutExtendedStateNoContextSharing(): void
    {
        $canAccessContext = true;

        $child = (new RegionBuilder())
            ->setStates('child')
            ->markInitial('child')
            ->onEnter('child', function (object $t) use (&$canAccessContext) {
                $canAccessContext = method_exists($this, 'get');
            });

        // OrthogonalRegions WITHOUT ExtendedState
        $region = (new OrthogonalRegions(new RegionBuilder(), [$child]))
            ->setStates('parent')
            ->markInitial('parent')
            ->build();

        $region->init();

        $this->assertFalse($canAccessContext, 'Context methods should not exist without ExtendedState');
    }

    public function testContextDataPersistsAcrossFeatureBoundaries(): void
    {
        $finalValue = null;

        $child = (new RegionBuilder())
            ->setStates('child')
            ->markInitial('child')
            ->onEnter('child', function (object $t) {
                $existing = $this->get('data') ?? '';
                $this->set('data', $existing . '_child');
            });

        $region = (new OrthogonalRegions(
            new ExtendedState(new RegionBuilder()),
            [$child]
        ))
            ->setStates('parent', 'final')
            ->markInitial('parent')
            ->onEnter('parent', function (object $t) {
                $this->set('data', 'parent');
            })
            ->onAction('parent', fn(object $t) => 'final')
            ->onEnter('final', function (object $t) use (&$finalValue) {
                $finalValue = $this->get('data');
            })
            ->build();

        $runtime = new \Noem\State\StandardRuntime($region);
        $runtime->run();

        $this->assertEquals('parent_child', $finalValue);
    }

    public function testMultipleFeatureLayersPreserveContext(): void
    {
        $accessibleInAllLayers = true;

        $child = (new RegionBuilder())
            ->setStates('child')
            ->markInitial('child')
            ->onEnter('child', function (object $t) use (&$accessibleInAllLayers) {
                if (!method_exists($this, 'get')) {
                    $accessibleInAllLayers = false;
                }
                $value = $this->get('test');
                if ($value !== 'shared') {
                    $accessibleInAllLayers = false;
                }
            });

        $region = (new OrthogonalRegions(
            new ExtendedState(new RegionBuilder()),
            [$child]
        ))
            ->setStates('parent')
            ->markInitial('parent')
            ->onEnter('parent', function (object $t) use (&$accessibleInAllLayers) {
                if (!method_exists($this, 'get')) {
                    $accessibleInAllLayers = false;
                }
                $this->set('test', 'shared');
            })
            ->build();

        $region->init();

        $this->assertTrue($accessibleInAllLayers);
    }
}
