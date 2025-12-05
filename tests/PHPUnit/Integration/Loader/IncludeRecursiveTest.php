<?php

declare(strict_types=1);

namespace Noem\State\Tests\Integration\Loader;

use Noem\State\Feature\Includes\IncludesFeature;
use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: !include re-parses included YAML with same helpers
 */
#[Group('loader')]
#[Group('yaml-include-helpers')]
class IncludeRecursiveTest extends TestCase
{
    public function testIncludeReParsesIncludedYamlWithSameHelpers(): void
    {
        $tempDir = sys_get_temp_dir() . '/test_' . uniqid();
        mkdir($tempDir);

        // Nested file contains state list
        $nestedFile = $tempDir . '/nested.yaml';
        file_put_contents($nestedFile, <<<YAML
- name: nestedState
YAML);

        // First file includes the nested file
        $firstFile = $tempDir . '/first.yaml';
        file_put_contents($firstFile, "states: !include nested.yaml");

        try {
            $yaml = "!include first.yaml";

            $builder = new RegionBuilder();
            $builder->enableFeatures(new IncludesFeature(), new RegionLoader());

            // All !include tags should be processed recursively
            $result = $builder->build([
                'loader' => [
                    'yaml' => $yaml,
                    'array' => [
                        'includes' => [
                            'basePath' => $tempDir,
                        ],
                    ],
                ],
            ]);

            $this->assertInstanceOf(\Noem\State\Region::class, $result);
        } finally {
            unlink($nestedFile);
            unlink($firstFile);
            rmdir($tempDir);
        }
    }
}
