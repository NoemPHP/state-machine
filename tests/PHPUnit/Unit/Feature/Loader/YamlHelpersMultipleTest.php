<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Loader;

use Noem\State\Feature\Loader\YamlHelpers;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: YamlHelpers registry allows features to register multiple helpers
 */
#[Group('loader'), Group('yaml-helpers')]
class YamlHelpersMultipleTest extends TestCase
{
    public function testRegistersMultipleHelpers(): void
    {
        // Arrange
        $registry = new YamlHelpers();
        $uppercase = fn(string $value) => strtoupper($value);
        $lowercase = fn(string $value) => strtolower($value);
        $reverse = fn(string $value) => strrev($value);

        // Act
        $registry->register('uppercase', $uppercase);
        $registry->register('lowercase', $lowercase);
        $registry->register('reverse', $reverse);

        // Assert
        $helpers = $registry->getHelpers();
        $this->assertCount(3, $helpers);
        $this->assertArrayHasKey('uppercase', $helpers);
        $this->assertArrayHasKey('lowercase', $helpers);
        $this->assertArrayHasKey('reverse', $helpers);
        $this->assertSame($uppercase, $helpers['uppercase']);
        $this->assertSame($lowercase, $helpers['lowercase']);
        $this->assertSame($reverse, $helpers['reverse']);
    }
}
