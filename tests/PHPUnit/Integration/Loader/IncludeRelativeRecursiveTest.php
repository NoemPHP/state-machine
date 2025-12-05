<?php

declare(strict_types=1);

namespace Noem\State\Tests\Integration\Loader;

use Noem\State\Feature\Includes\IncludesFeature;
use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: !includeRelative re-parses included YAML with updated helper context
 */
#[Group('loader')]
#[Group('yaml-include-helpers')]
class IncludeRelativeRecursiveTest extends TestCase
{
    public function testIncludeRelativeReParsesWithUpdatedContext(): void
    {
        $baseDir = sys_get_temp_dir() . '/test_base_' . uniqid();
        $subDir = $baseDir . '/sub';
        mkdir($baseDir);
        mkdir($subDir);

        // Nested file in subdirectory
        $nestedFile = $subDir . '/nested.yaml';
        file_put_contents($nestedFile, <<<YAML
- name: nestedState
YAML);

        // First file uses !includeRelative for nested file
        $firstFile = $subDir . '/first.yaml';
        file_put_contents($firstFile, "states: !includeRelative nested.yaml");

        try {
            $yaml = "!includeRelative sub/first.yaml";

            $builder = new RegionBuilder();
            $builder->enableFeatures(new IncludesFeature(), new RegionLoader());

            // The second !includeRelative should resolve relative to first.yaml's directory (subDir)
            $result = $builder->build([
                'loader' => [
                    'yaml' => $yaml,
                    'array' => [
                        'includes' => [
                            'basePath' => $baseDir,
                        ],
                    ],
                ],
            ]);

            $this->assertInstanceOf(\Noem\State\Region::class, $result);
        } finally {
            unlink($nestedFile);
            unlink($firstFile);
            rmdir($subDir);
            rmdir($baseDir);
        }
    }
}
