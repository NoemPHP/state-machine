<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Interaction;

use Noem\State\RegionBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @spec interact() method throws InvalidArgumentException when parameter is not InteractionRequest
 * @see specs/features/interaction.yaml - feature-integration-bound-access
 */
class InteractThrowsOnInvalidParameterTest extends TestCase
{
    public function testThrowsInvalidArgumentException(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(
            new \Noem\State\Feature\ExtendedState\ExtendedState(),
            new \Noem\State\Feature\Interaction\InteractionFeature()
        );
        $builder->addState('test')->onEnter('test', function (object $trigger) {
                $this->interact(new \stdClass());
        });
        $builder->initialState('test');

        $region = $builder->build();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('interact() requires InteractionRequest');

        $runtime = new \Noem\State\StandardRuntime($region);
        $runtime->run();
    }
}
