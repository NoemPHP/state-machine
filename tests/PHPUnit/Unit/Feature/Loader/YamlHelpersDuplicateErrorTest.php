<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Loader;

use Noem\State\Feature\Loader\YamlHelpers;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: YamlHelpers registry throws exception when duplicate helper name registered
 */
#[Group('loader'), Group('yaml-helpers')]
class YamlHelpersDuplicateErrorTest extends TestCase
{
    public function testThrowsExceptionOnDuplicateHelperName(): void
    {
        // Arrange
        $registry = new YamlHelpers();
        $helper1 = fn(string $value) => strtoupper($value);
        $helper2 = fn(string $value) => strtolower($value);

        $registry->register('transform', $helper1);

        // Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Helper "transform" is already registered');

        // Act
        $registry->register('transform', $helper2);
    }
}
