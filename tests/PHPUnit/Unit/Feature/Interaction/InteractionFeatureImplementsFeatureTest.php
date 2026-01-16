<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Interaction;

use Noem\State\Feature\Feature;
use Noem\State\Feature\Interaction\InteractionFeature;
use PHPUnit\Framework\TestCase;

/**
 * @spec InteractionFeature implements Feature interface
 * @see specs/features/interaction.yaml - feature-integration-bound-access
 */
class InteractionFeatureImplementsFeatureTest extends TestCase
{
    public function testImplementsFeatureInterface(): void
    {
        $feature = new InteractionFeature();

        $this->assertInstanceOf(Feature::class, $feature);
    }
}
