<?php

declare(strict_types=1);

namespace Noem\State\Tests\Integration\Loader;

use Noem\State\Feature\Includes\IncludesFeature;
use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Nested !includeRelative maintains relative path chain through current file tracking
 */
#[Group('loader')]
#[Group('yaml-include-helpers')]
class IncludeRelativeNestedTest extends TestCase
{
    public function testNestedIncludeRelativeMaintainsPathChain(): void
    {
        // Create nested directory structure
        $baseDir = sys_get_temp_dir() . '/test_base_' . uniqid();
        $subDir1 = $baseDir . '/level1';
        $subDir2 = $subDir1 . '/level2';
        mkdir($baseDir);
        mkdir($subDir1);
        mkdir($subDir2);

        // Deepest file
        $level2File = $subDir2 . '/deep.yaml';
        file_put_contents($level2File, "value");

        // Middle file - includes relative to its own directory
        $level1File = $subDir1 . '/middle.yaml';
        file_put_contents(
            $level1File,
            /** @lang yaml */ <<<YAML
- target: !includeRelative level2/deep.yaml
YAML

        );

        // Top file - includes relative to its own directory
        $topFile = $baseDir . '/top.yaml';
        file_put_contents(
            $topFile,
            /** @lang yaml */ <<<YAML
- name: 'foo'
  transitions: !includeRelative level1/middle.yaml
YAML
);

        try {
            $yaml = "states: !includeRelative top.yaml";

            $builder = new RegionBuilder();
            $builder->enableFeatures(new IncludesFeature(), new RegionLoader());

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
            unlink($level2File);
            unlink($level1File);
            unlink($topFile);
            rmdir($subDir2);
            rmdir($subDir1);
            rmdir($baseDir);
        }
    }
}
