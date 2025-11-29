<?php

declare(strict_types=1);

namespace Tests\PHPUnit\Unit\Feature\Loader\Holon;

use Noem\State\Feature\Loader\Holon;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Acceptance Criterion: Holon throws RuntimeException for missing Feature class
 */
#[Group('loader'), Group('holon'), Group('holon-error-handling')]
class MissingFeatureErrorTest extends TestCase
{
    public function testThrowsExceptionForNonExistentFeatureClass(): void
    {
        // Arrange
        $yaml = <<<YAML
machine:
  features:
    - class: Noem\State\Feature\NonExistentFeature
states:
  - name: start
    initial: true
    final: true
YAML;

        // Assert
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Feature class 'Noem\\State\\Feature\\NonExistentFeature' does not exist");
        
        // Act
        Holon::fromYaml($yaml);
    }
    
    public function testExceptionIncludesFeatureClassName(): void
    {
        // Arrange
        $yaml = <<<YAML
machine:
  features:
    - class: My\Custom\MissingFeature
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
            $this->assertStringContainsString('My\\Custom\\MissingFeature', $e->getMessage());
            $this->assertStringContainsString('does not exist', $e->getMessage());
        }
    }
    
    public function testThrowsExceptionForEmptyFeatureClass(): void
    {
        // Arrange
        $yaml = <<<YAML
machine:
  features:
    - class: ""
states:
  - name: start
    initial: true
    final: true
YAML;

        // Assert
        $this->expectException(RuntimeException::class);
        
        // Act
        Holon::fromYaml($yaml);
    }
    
    public function testAcceptsValidFeatureClass(): void
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
