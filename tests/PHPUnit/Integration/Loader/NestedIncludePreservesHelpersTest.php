<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Integration\Loader;

use Noem\State\Chains\ChainMail;
use Noem\State\Feature\Includes\IncludesFeature;
use Noem\State\Feature\Loader\ConvertYaml;
use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\Feature\Loader\YamlHelpers;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Nested !include preserves all registered helpers from YamlHelpers registry
 */
#[Group('loader'), Group('includes'), Group('integration')]
class NestedIncludePreservesHelpersTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/include_helpers_test_' . uniqid();
        mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->tempDir . '/*'));
        rmdir($this->tempDir);
    }

    public function testNestedIncludesPreserveRegistryHelpers(): void
    {
        // Arrange - Create deeply nested includes using multiple custom helpers
        file_put_contents($this->tempDir . '/level3.yml', 'deep: !helper3 "level3"');
        file_put_contents($this->tempDir . '/level2.yml', <<<YAML
mid: !helper2 "level2"
nested: !include 'level3.yml'
YAML
        );
        file_put_contents($this->tempDir . '/level1.yml', <<<YAML
top: !helper1 "level1"
nested: !include 'level2.yml'
YAML
        );

        $registry = new YamlHelpers();
        $registry->register('helper1', fn(string $v) => "h1:{$v}");
        $registry->register('helper2', fn(string $v) => "h2:{$v}");
        $registry->register('helper3', fn(string $v) => "h3:{$v}");

        $converter = new ConvertYaml($registry);

        // Define include helper that recursively uses itself
        $includeHelper = null;
        $includeHelper = function(string $path) use ($converter, &$includeHelper) {
            $content = file_get_contents($this->tempDir . '/' . $path);
            // Pass the include helper recursively so nested includes work
            return $converter->fromString($content, ['include' => $includeHelper]);
        };

        // Act - Parse with nested includes
        $result = $converter->fromString(
            file_get_contents($this->tempDir . '/level1.yml'),
            ['include' => $includeHelper]
        );

        // Assert - All helpers should work at all nesting levels
        $this->assertEquals([
            'top' => 'h1:level1',
            'nested' => [
                'mid' => 'h2:level2',
                'nested' => [
                    'deep' => 'h3:level3',
                ],
            ],
        ], $result);
    }
}
