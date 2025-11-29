<?php

declare(strict_types=1);

namespace Tests\PHPUnit\Unit\Feature\Loader\Holon;

use Noem\State\Feature\Loader\Holon;
use Noem\State\Region;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Holon loads machine from file path
 */
#[Group('loader'), Group('holon'), Group('holon-bootstrapping')]
class YamlFileLoadingTest extends TestCase
{
    public function testLoadsFromFilePath(): void
    {
        // Arrange - Create a temporary YAML file
        $tempFile = tempnam(sys_get_temp_dir(), 'holon_test_');
        $yaml = <<<YAML
states:
  - name: loaded
    initial: true
    final: true
YAML;
        file_put_contents($tempFile, $yaml);
        
        try {
            // Act
            $region = Holon::fromYaml($tempFile);
            
            // Assert
            $this->assertInstanceOf(Region::class, $region);
            $this->assertEquals('loaded', $region->currentState());
            $this->assertTrue($region->isFinal());
        } finally {
            // Cleanup
            unlink($tempFile);
        }
    }
    
    public function testDetectsFilePathWithoutNewlines(): void
    {
        // Arrange - file path has no newlines, so should be detected as file
        $tempFile = tempnam(sys_get_temp_dir(), 'holon_test_');
        $yaml = <<<YAML
states:
  - name: fromfile
    initial: true
    final: true
YAML;
        file_put_contents($tempFile, $yaml);
        
        try {
            // Act - the path has no newlines, triggering file read
            $region = Holon::fromYaml($tempFile);
            
            // Assert
            $this->assertInstanceOf(Region::class, $region);
            $this->assertEquals('fromfile', $region->currentState());
        } finally {
            unlink($tempFile);
        }
    }
}
