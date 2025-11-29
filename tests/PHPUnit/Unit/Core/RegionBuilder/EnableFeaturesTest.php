<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Core\RegionBuilder;

use Noem\State\Feature\Feature;
use Noem\State\Middleware\ChainMail;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: A builder can register features using enableFeatures
 */
#[Group('region-builder')]
#[Group('feature-registration')]
class EnableFeaturesTest extends TestCase
{
    public function testEnableFeaturesAcceptsSingleFeature(): void
    {
        $builder = new RegionBuilder();
        $invoked = false;

        $feature = new class($invoked) implements Feature {
            public function __construct(private bool &$invoked) {}

            public function __invoke(ChainMail $chainMail): void
            {
                $this->invoked = true;
            }
        };

        $result = $builder->enableFeatures($feature);

        $this->assertSame($builder, $result, 'enableFeatures should return builder for chaining');

        // Features are invoked during build(), not enableFeatures()
        $this->assertFalse($invoked, 'Feature should NOT be invoked during enableFeatures()');

        $builder->setStates('a', 'b')->build();

        $this->assertTrue($invoked, 'Feature should be invoked during build()');
    }
    
    public function testEnableFeaturesInvokesFeature(): void
    {
        $builder = new RegionBuilder();
        $receivedChainMail = null;

        $feature = new class($receivedChainMail) implements Feature {
            public function __construct(private mixed &$receivedChainMail) {}

            public function __invoke(ChainMail $chainMail): void
            {
                $this->receivedChainMail = $chainMail;
            }
        };

        $builder->enableFeatures($feature);

        // Feature not yet invoked during enableFeatures()
        $this->assertNull($receivedChainMail, 'Feature should NOT be invoked during enableFeatures()');

        $builder->setStates('a', 'b')->build();

        // Feature invoked during build()
        $this->assertInstanceOf(
            ChainMail::class,
            $receivedChainMail,
            'Feature should receive ChainMail instance during build()'
        );
        $this->assertSame(
            $builder->chainMail,
            $receivedChainMail,
            'Feature should receive the builder\'s ChainMail'
        );
    }
}
