<?php

declare(strict_types=1);

namespace Noem\State\Tests\Integration\Loader;

use Noem\State\Feature\Includes\IncludesFeature;
use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: !includeRelative handles absolute paths without relative resolution
 */
#[Group('loader')]
#[Group('yaml-include-helpers')]
class IncludeRelativeAbsolutePathTest extends TestCase
{
    public function testIncludeRelativeHandlesAbsolutePathsWithoutRelativeResolution(): void
    {
        $tempDir = sys_get_temp_dir() . '/test_' . uniqid();
        mkdir($tempDir);

        $includedFile = $tempDir . '/absolute.yaml';
        file_put_contents($includedFile, <<<YAML
- name: absoluteState
YAML);

        try {
            // Use absolute path in !includeRelative tag
            $yaml = "states: !includeRelative {$includedFile}";

            $builder = new RegionBuilder();
            $builder->enableFeatures(new IncludesFeature(), new RegionLoader());

            // Absolute path should bypass relative file resolution
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
