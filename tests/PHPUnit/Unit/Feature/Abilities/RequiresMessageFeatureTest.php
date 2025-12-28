<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Abilities;

use Noem\State\Feature\Abilities\AbilitiesFeature;
use Noem\State\Feature\Message\MessageFeature;
use Noem\State\Feature\RequiresFeature;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: AbilitiesFeature requires MessageFeature dependency
 *
 * Intent: Enforces MessageFeature presence for correlation-based response handling,
 * preventing runtime failures from missing infrastructure
 */
#[Group('abilities')]
#[Group('feature-registration')]
class RequiresMessageFeatureTest extends TestCase
{
    public function testAbilitiesFeatureDeclaresMessageFeatureDependency(): void
    {
        $reflection = new \ReflectionClass(AbilitiesFeature::class);
        $attributes = $reflection->getAttributes(RequiresFeature::class);

        // Find the attribute that declares MessageFeature dependency
        $messageFeatureDependency = null;
        foreach ($attributes as $attr) {
            $instance = $attr->newInstance();
            if ($instance->featureFQCN === MessageFeature::class) {
                $messageFeatureDependency = $instance;
                break;
            }
        }

        $this->assertNotNull(
            $messageFeatureDependency,
            'AbilitiesFeature should declare dependency on MessageFeature via RequiresFeature attribute'
        );

        $this->assertSame(
            MessageFeature::class,
            $messageFeatureDependency->featureFQCN,
            'RequiresFeature should reference MessageFeature class'
        );
    }
}
