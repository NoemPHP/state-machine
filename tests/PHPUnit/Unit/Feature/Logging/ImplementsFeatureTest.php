<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Logging;

use Noem\State\Feature\Feature;
use Noem\State\Feature\Logging\LoggingFeature;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LoggingFeature::class)]
class ImplementsFeatureTest extends TestCase
{
    public function testLoggingFeatureImplementsFeatureInterface(): void
    {
        $feature = new LoggingFeature();

        $this->assertInstanceOf(Feature::class, $feature);
    }

    public function testLoggingFeatureIsInvokable(): void
    {
        $feature = new LoggingFeature();

        $this->assertTrue(is_callable($feature));
    }
}
