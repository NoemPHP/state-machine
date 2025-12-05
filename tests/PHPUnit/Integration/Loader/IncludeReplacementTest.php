<?php

declare(strict_types=1);

namespace Noem\State\Tests\Integration\Loader;

use Noem\State\Feature\Includes\IncludesFeature;
use Noem\State\Feature\Loader\ConvertYaml;
use Noem\State\Feature\Loader\RegionLoader;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: !include and !includeRelative replace tag with parsed YAML content
 */
#[Group('loader')]
#[Group('yaml-include-helpers')]
class IncludeReplacementTest extends TestCase
{
    public function testIncludeTagsReplacedWithParsedYamlContent(): void
    {
        $tempDir = sys_get_temp_dir() . '/test_' . uniqid();
        mkdir($tempDir);

        $includedFile = $tempDir . '/included.yaml';
        file_put_contents($includedFile, <<<'YAML'
key1: value1
key2: value2
YAML
        );

        try {
            $yaml = "data: !include included.yaml";

            $convertYaml = new ConvertYaml();

            // Create helpers that will be used
            $helpers = [
                'include' => function (string $path) use ($tempDir) {
                    // This is what the helper should do - load and parse YAML
                    $content = file_get_contents($tempDir . '/' . $path);
                    return yaml_parse($content);
                },
            ];

            $result = $convertYaml->fromString($yaml, $helpers);

            // The !include tag should be replaced with the parsed YAML content
            $this->assertIsArray($result);
            $this->assertArrayHasKey('data', $result);
            $this->assertIsArray($result['data']);
            $this->assertSame('value1', $result['data']['key1']);
            $this->assertSame('value2', $result['data']['key2']);
        } finally {
            unlink($includedFile);
            rmdir($tempDir);
        }
    }
}
