<?php

declare(strict_types=1);

namespace Tests\PHPUnit\Unit\Feature\Loader\Holon;

use Noem\State\Feature\Loader\Holon;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Acceptance Criterion: Holon validates Feature interface on instantiation
 */
#[Group('loader'), Group('holon'), Group('holon-error-handling')]
class InvalidFeatureErrorTest extends TestCase
{
    public function testThrowsExceptionForNonFeatureClass(): void
    {
        // Arrange
        $yaml = <<<YAML
machine:
  features:
    - class: stdClass
states:
  - name: start
    initial: true
    final: true
YAML;

        // Assert
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Class 'stdClass' must implement Feature interface");

        // Act
        Holon::fromYaml($yaml);
    }

    public function testExceptionIdentifiesInvalidClass(): void
    {
        // Arrange
        $yaml = <<<YAML
machine:
  features:
    - class: ArrayObject
states:
  - name: start
    initial: true
    final: true
YAML;

        // Act & Assert
        try {
            Holon::fromYaml($yaml);
            $this->fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('ArrayObject', $e->getMessage());
            $this->assertStringContainsString('Feature interface', $e->getMessage());
        }
    }

    public function testValidatesAllFeaturesInList(): void
    {
        // Arrange - First valid, second invalid
        $yaml = <<<YAML
machine:
  features:
    - class: Noem\State\Feature\Loader\RegionLoader
    - class: DateTime
states:
  - name: start
    initial: true
    final: true
YAML;

        // Assert - should fail on second feature
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Class 'DateTime' must implement Feature interface");

        // Act
        Holon::fromYaml($yaml);
    }

    public function testAcceptsValidFeatureImplementation(): void
    {
        // Arrange
        $yaml = <<<YAML
machine:
  features:
    - class: Noem\State\Feature\Loader\RegionLoader
states:
  - name: start
    initial: true
    final: true
YAML;

        // Act
        $region = Holon::fromYaml($yaml);

        // Assert - no exception thrown
        $this->assertNotNull($region);
    }
}
