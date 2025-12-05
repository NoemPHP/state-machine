<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Loader;

use Noem\State\Feature\Loader\YamlHelpers;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: YamlHelpers registry returns empty array when no helpers registered
 */
#[Group('loader'), Group('yaml-helpers')]
class YamlHelpersEmptyTest extends TestCase
{
    public function testReturnsEmptyArrayWhenNoHelpersRegistered(): void
    {
        // Arrange
        $registry = new YamlHelpers();

        // Act
        $helpers = $registry->getHelpers();

        // Assert
        $this->assertIsArray($helpers);
        $this->assertEmpty($helpers);
    }
}
