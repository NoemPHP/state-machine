<?php

declare(strict_types=1);

namespace Noem\State\Tests\Integration\Loader;

use Noem\State\Feature\Includes\IncludesFeature;
use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: !includeRelative first invocation resolves paths relative to basePath
 */
#[Group('loader')]
#[Group('yaml-include-helpers')]
class IncludeRelativeFirstInvocationTest extends TestCase
{
    public function testIncludeRelativeFirstInvocationUsesBasePath(): void
    {
        // Create temp directory structure
        $baseDir = sys_get_temp_dir() . '/test_base_' . uniqid();
        mkdir($baseDir);

        $includedFile = $baseDir . '/first.yaml';
        file_put_contents($includedFile, <<<YAML
- name: firstState
YAML);

        try {
            $yaml = "states: !includeRelative first.yaml";

            $builder = new RegionBuilder();
            $builder->enableFeatures(new IncludesFeature(), new RegionLoader());

            // First invocation should use basePath since no current file context exists
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
            unlink($includedFile);
            rmdir($baseDir);
        }
    }
}
