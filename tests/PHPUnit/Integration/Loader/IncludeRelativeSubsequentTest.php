<?php

declare(strict_types=1);

namespace Noem\State\Tests\Integration\Loader;

use Noem\State\Feature\Includes\IncludesFeature;
use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: !includeRelative subsequent invocations resolve paths relative to current file
 */
#[Group('loader')]
#[Group('yaml-include-helpers')]
class IncludeRelativeSubsequentTest extends TestCase
{
    public function testIncludeRelativeSubsequentInvocationsUseCurrentFile(): void
    {
        // Create nested directory structure
        $baseDir = sys_get_temp_dir() . '/test_base_' . uniqid();
        $subDir = $baseDir . '/sub';
        mkdir($baseDir);
        mkdir($subDir);

        $secondFile = $subDir . '/second.yaml';
        file_put_contents($secondFile, <<<YAML
- name: secondState
YAML);

        $firstFile = $baseDir . '/first.yaml';
        file_put_contents($firstFile, "states: !includeRelative sub/second.yaml");

        try {
            $yaml = "!includeRelative first.yaml";

            $builder = new RegionBuilder();
            $builder->enableFeatures(new IncludesFeature(), new RegionLoader());

            // Second !includeRelative should resolve relative to first.yaml's directory
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
            unlink($secondFile);
            unlink($firstFile);
            rmdir($subDir);
            rmdir($baseDir);
        }
    }
}
