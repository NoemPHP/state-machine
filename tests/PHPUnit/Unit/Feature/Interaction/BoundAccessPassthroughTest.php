<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Interaction;

use Noem\State\RegionBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @spec BoundAccess passes non-interact method calls through unchanged
 * @see specs/features/interaction.yaml - feature-integration-bound-access
 */
class BoundAccessPassthroughTest extends TestCase
{
    public function testPassesThroughNonInteractMethods(): void
    {
        $getValue = null;

        $builder = new RegionBuilder();
        $builder->enableFeatures(
            new \Noem\State\Feature\ExtendedState\ExtendedState(),
            new \Noem\State\Feature\Interaction\InteractionFeature()
        );
        $builder->addState('test')->onEnter('test', function (object $trigger) use (&$getValue) {
                // These ExtendedState methods should work unchanged
                $this->set('testKey', 'testValue');
                $getValue = $this->get('testKey');
        });
        $builder->initialState('test');

        $region = $builder->build();
        $region->trigger((object)[]);

        $this->assertSame('testValue', $getValue);
    }
}
