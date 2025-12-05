<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Loader;

use Noem\State\Feature\Loader\ConvertYaml;
use Noem\State\Feature\Loader\YamlHelpers;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: ConvertYaml merges registry helpers with constructor-provided helpers
 */
#[Group('loader'), Group('yaml-helpers')]
class ConvertYamlMergesHelpersTest extends TestCase
{
    public function testMergesRegistryWithConstructorHelpers(): void
    {
        // Arrange
        $registry = new YamlHelpers();
        $registry->register('registry', fn(string $value) => "registry: {$value}");

        $constructorHelpers = [
            'constructor' => fn(string $value) => "constructor: {$value}",
        ];

        $converter = new ConvertYaml($registry, $constructorHelpers);
        $yaml = <<<YAML
registry: !registry 'test1'
constructor: !constructor 'test2'
YAML;

        // Act
        $result = $converter->fromString($yaml);

        // Assert
        $this->assertEquals([
            'registry' => 'registry: test1',
            'constructor' => 'constructor: test2',
        ], $result);
    }

    public function testConstructorHelpersTakePrecedenceOverRegistry(): void
    {
        // Arrange
        $registry = new YamlHelpers();
        $registry->register('helper', fn(string $value) => "registry: {$value}");

        $constructorHelpers = [
            'helper' => fn(string $value) => "constructor: {$value}",
        ];

        $converter = new ConvertYaml($registry, $constructorHelpers);
        $yaml = "value: !helper 'test'";

        // Act
        $result = $converter->fromString($yaml);

        // Assert - Constructor helper should win
        $this->assertEquals(['value' => 'constructor: test'], $result);
    }
}
