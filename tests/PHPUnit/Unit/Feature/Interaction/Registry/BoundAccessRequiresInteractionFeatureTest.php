<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Interaction\Registry;

use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\Interaction\InteractionDefinition;
use Noem\State\Feature\Interaction\InteractionRegistryFeature;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InteractionRegistryFeature::class)]
class BoundAccessRequiresInteractionFeatureTest extends TestCase
{
    public function testBoundAccessRegisterInteractionRequiresInteractionFeatureLoaded(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(
            new ExtendedState()
            // Note: InteractionFeature NOT loaded
        );

        $builder->setStates('ready');
        $builder->markInitial('ready');
        $exceptionCaught = false;
        $builder->onEnter('ready', function (object $trigger) use (&$exceptionCaught): void {
            // This should fail or be a no-op since registerInteraction() won't be wired
            // without InteractionRegistryFeature
            try {
                $this->registerInteraction(
                    'test_id',
                    new InteractionDefinition(
                        id: 'test_id',
                        type: 'confirm',
                        state: 'ready',
                        question: 'Test?'
                    )
                );
                // Should not reach here
            } catch (\RuntimeException $e) {
                // Expected - method doesn't exist without feature
                if (str_contains($e->getMessage(), 'registerInteraction')) {
                    $exceptionCaught = true;
                }
            }
        });

        $region = $builder->build();
        $region->trigger((object)[]);

        $this->assertTrue($exceptionCaught, 'Expected registerInteraction to fail without InteractionRegistryFeature');
    }
}
