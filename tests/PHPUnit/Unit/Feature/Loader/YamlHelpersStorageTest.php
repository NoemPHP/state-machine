<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Loader;

use Noem\State\Feature\Loader\YamlHelpers;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: YamlHelpers registry stores helper functions by name
 */
#[Group('loader'), Group('yaml-helpers')]
class YamlHelpersStorageTest extends TestCase
{
    public function testStoresHelperByName(): void
    {
        // Arrange
        $registry = new YamlHelpers();
        $helper = fn(string $value) => strtoupper($value);

        // Act
        $registry->register('uppercase', $helper);

        // Assert
        $helpers = $registry->getHelpers();
        $this->assertArrayHasKey('uppercase', $helpers);
        $this->assertSame($helper, $helpers['uppercase']);
    }
}
