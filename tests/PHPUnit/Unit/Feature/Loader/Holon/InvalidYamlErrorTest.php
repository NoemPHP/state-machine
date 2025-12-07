<?php

declare(strict_types=1);

namespace Tests\PHPUnit\Unit\Feature\Loader\Holon;

use Noem\State\Feature\Loader\Holon;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Exception\ParseException;

/**
 * Acceptance Criterion: Holon throws exception for invalid YAML syntax
 */
#[Group('loader'), Group('holon'), Group('holon-error-handling')]
class InvalidYamlErrorTest extends TestCase
{
    public function testThrowsExceptionForMalformedYaml(): void
    {
        // Arrange
        $invalidYaml = <<<YAML
states:
  - name: start
    initial: true
  - name: end
    final: true
  tabs and spaces mixed:
\t\tmixed: problem
YAML;

        // Assert
        $this->expectException(ParseException::class);

        // Act
        Holon::fromYaml($invalidYaml);
    }

    public function testThrowsExceptionForInvalidYamlStructure(): void
    {
        // Arrange
        $invalidYaml = <<<YAML
states:
  - name: start
    initial: true
  - unclosed: [bracket
YAML;

        // Assert
        $this->expectException(ParseException::class);

        // Act
        Holon::fromYaml($invalidYaml);
    }

    public function testThrowsExceptionForInvalidIndentation(): void
    {
        // Arrange
        $invalidYaml = <<<YAML
states:
  - name: start
initial: true
  - name: end
    final: true
YAML;

        // Assert
        $this->expectException(ParseException::class);

        // Act
        Holon::fromYaml($invalidYaml);
    }

    public function testAcceptsValidYaml(): void
    {
        // Arrange
        $validYaml = <<<YAML
states:
  - name: start
    initial: true
    final: true
YAML;

        // Act
        $region = Holon::fromYaml($validYaml);

        // Assert - no exception thrown
        $this->assertNotNull($region);
    }
}
