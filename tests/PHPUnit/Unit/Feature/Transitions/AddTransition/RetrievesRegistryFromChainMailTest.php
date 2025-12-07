<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Transitions\AddTransition;

use Noem\State\Feature\Transitions\AddTransition;
use Noem\State\Feature\Transitions\TransitionRegistry;
use Noem\State\Feature\Transitions\TransitionsFeature;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: AddTransition retrieves TransitionRegistry from builder's ChainMail
 */
#[Group('transitions')]
#[Group('add-transition-buildstep')]
class RetrievesRegistryFromChainMailTest extends TestCase
{
    public function testRetrievesRegistryFromChainMail(): void
    {
        $builder = (new RegionBuilder())
            ->enableFeatures(new TransitionsFeature())
            ->setStates('start', 'end')
            ->addBuildStep(new AddTransition('start', 'end'));

        // Build the region - this triggers AddTransition callback
        $region = $builder->build();

        // Verify registry was accessed and used
        $registry = $builder->chainMail->get(TransitionRegistry::class);
        $transitions = $registry->getTransitionsForState($region, 'start');

        $this->assertInstanceOf(TransitionRegistry::class, $registry);
        $this->assertArrayHasKey('end', $transitions);
    }
}
