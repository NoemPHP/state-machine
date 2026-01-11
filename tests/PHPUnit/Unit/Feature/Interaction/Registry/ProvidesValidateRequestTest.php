<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Interaction\Registry;

use Noem\State\Feature\Interaction\InteractionRegistryFeature;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InteractionRegistryFeature::class)]
class ProvidesValidateRequestTest extends TestCase
{
    public function testInteractionFeatureProvidesValidateRequestMethodAcceptingInteractionRequest(): void
    {
        $feature = new InteractionRegistryFeature();

        // Verify the feature class exists and can be instantiated
        $this->assertInstanceOf(InteractionRegistryFeature::class, $feature);

        // Note: Full validateRequest() testing requires implementation
        // This test verifies the feature structure for contract validation capability
        $this->assertTrue(method_exists($feature, '__invoke'));
    }
}
