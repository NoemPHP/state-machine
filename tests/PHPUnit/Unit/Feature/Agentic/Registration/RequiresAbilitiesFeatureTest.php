<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Agentic\Registration;

use Noem\State\Feature\Agentic\AgenticFeature;
use Noem\State\Middleware\ChainMail;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: weave() method requires AbilitiesFeature dependency at registration time
 *
 * Criticality: constraint
 * Intent: Enforces AbilitiesFeature presence for tool enumeration, preventing runtime
 * failures from missing infrastructure
 *
 * @spec /specs/features/agentic.yaml:24-27
 */
#[Group('ai'), Group('weave'), Group('registration')]
final class RequiresAbilitiesFeatureTest extends TestCase
{
    #[Test]
    public function weaveRequiresAbilitiesFeature(): void
    {
        // Verify AgenticFeature has #[RequiresFeature(AbilitiesFeature::class)] attribute
        $reflection = new \ReflectionClass(AgenticFeature::class);
        $attributes = $reflection->getAttributes(\Noem\State\Feature\RequiresFeature::class);

        $this->assertNotEmpty($attributes, 'AgenticFeature should have RequiresFeature attribute');

        // Verify one of the attributes is for AbilitiesFeature
        $hasAbilitiesFeature = false;
        foreach ($attributes as $attribute) {
            $args = $attribute->getArguments();
            if (in_array(\Noem\State\Feature\Abilities\AbilitiesFeature::class, $args, true)) {
                $hasAbilitiesFeature = true;
                break;
            }
        }

        $this->assertTrue($hasAbilitiesFeature, 'AgenticFeature should require AbilitiesFeature');
    }
}
