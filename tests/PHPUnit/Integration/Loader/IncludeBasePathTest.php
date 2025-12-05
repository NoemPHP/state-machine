<?php

declare(strict_types=1);

namespace Noem\State\Tests\Integration\Loader;

use Noem\State\Feature\Includes\IncludesFeature;
use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: !include resolves paths relative to configured basePath
 */
#[Group('loader')]
#[Group('yaml-include-helpers')]
class IncludeBasePathTest extends TestCase
{
    public function testIncludeResolvesPathsRelativeToConfiguredBasePath(): void
    {
        // Create temp directory structure
        $baseDir = sys_get_temp_dir() . '/test_base_' . uniqid();
        mkdir($baseDir);

        // Create a state definition in the included file
        $includedFile = $baseDir . '/config.yaml';
        file_put_contents($includedFile, <<<YAML
- name: includedState
YAML);

        try {
            // Use !include to load states from basePath-relative file
            $yaml = <<<'YAML'
states: !include config.yaml
YAML;

            $builder = new RegionBuilder();
            $builder->enableFeatures(new IncludesFeature(), new RegionLoader());

            // Configure basePath - !include should resolve "config.yaml" relative to this
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

            // File should be resolved relative to basePath
            $this->assertInstanceOf(\Noem\State\Region::class, $result);
        } finally {
            unlink($includedFile);
            rmdir($baseDir);
        }
    }
}
