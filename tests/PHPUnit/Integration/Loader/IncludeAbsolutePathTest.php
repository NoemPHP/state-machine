<?php

declare(strict_types=1);

namespace Noem\State\Tests\Integration\Loader;

use Noem\State\Feature\Includes\IncludesFeature;
use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: !include handles absolute paths without basePath modification
 */
#[Group('loader')]
#[Group('yaml-include-helpers')]
class IncludeAbsolutePathTest extends TestCase
{
    public function testIncludeHandlesAbsolutePathsWithoutBasePathModification(): void
    {
        $tempDir = sys_get_temp_dir() . '/test_' . uniqid();
        mkdir($tempDir);

        $includedFile = $tempDir . '/absolute.yaml';
        file_put_contents($includedFile, <<<YAML
- name: absoluteState
YAML);

        try {
            // Use absolute path in !include tag
            $yaml = "states: !include {$includedFile}";

            $builder = new RegionBuilder();
            $builder->enableFeatures(new IncludesFeature(), new RegionLoader());

            // Absolute path should bypass basePath resolution
            $result = $builder->build([
                'loader' => [
                    'yaml' => $yaml,
                    'array' => [
                        'includes' => [
                            'basePath' => '/some/other/path',  // Should be ignored for absolute paths
                        ],
                    ],
                ],
            ]);

            $this->assertInstanceOf(\Noem\State\Region::class, $result);
        } finally {
            unlink($includedFile);
            rmdir($tempDir);
        }
    }
}
