<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Interaction;

use Noem\State\RegionBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @spec interact() method validates first parameter is InteractionRequest instance
 * @see specs/features/interaction.yaml - feature-integration-bound-access
 */
class InteractValidatesParameterTypeTest extends TestCase
{
    public function testValidatesParameterType(): void
    {
        $exceptionCaught = false;

        $builder = new RegionBuilder();
        $builder->enableFeatures(
            new \Noem\State\Feature\ExtendedState\ExtendedState(),
            new \Noem\State\Feature\Message\MessageFeature(),
            new \Noem\State\Feature\Interaction\InteractionFeature(),
            new \Noem\State\Feature\Interaction\InteractionRegistryFeature()
        );
        $builder->addState('test')->onEnter('test', function (object $trigger) use (&$exceptionCaught) {
            try {
                // Pass invalid parameter (not InteractionRequest)
                $this->interact('invalid');
            } catch (\InvalidArgumentException $e) {
                $exceptionCaught = true;
            }
        });
        $builder->initialState('test');

        $region = $builder->build();
        $runtime = new \Noem\State\StandardRuntime($region);
        $runtime->run();

        $this->assertTrue($exceptionCaught, 'interact() should validate parameter type');
    }
}
