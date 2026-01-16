<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Presentation;

use Noem\State\Feature\Feature;
use Noem\State\Feature\Presentation\PresentationFeature;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: PresentationFeature implements Feature interface
 * Intent: Ensures feature integrates correctly with ChainMail feature loading system
 * Criticality: contract
 */
final class ImplementsFeatureTest extends TestCase
{
    public function testPresentationFeatureImplementsFeatureInterface(): void
    {
        $feature = new PresentationFeature();

        $this->assertInstanceOf(Feature::class, $feature);
    }

    public function testPresentationFeatureIsInvokable(): void
    {
        $feature = new PresentationFeature();

        $this->assertTrue(method_exists($feature, '__invoke'));
    }
}
