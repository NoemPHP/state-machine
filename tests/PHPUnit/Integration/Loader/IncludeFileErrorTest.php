<?php

declare(strict_types=1);

namespace Noem\State\Tests\Integration\Loader;

use Noem\State\Feature\Includes\IncludesFeature;
use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Include helpers throw RuntimeException when LoadFile chain fails
 */
#[Group('loader')]
#[Group('yaml-include-helpers')]
class IncludeFileErrorTest extends TestCase
{
    public function testIncludeHelpersThrowExceptionWhenFileNotFound(): void
    {
        $tempDir = sys_get_temp_dir() . '/test_' . uniqid();
        mkdir($tempDir);

        try {
            $yaml = "data: !include nonexistent.yaml";

            $builder = new RegionBuilder();
            $builder->enableFeatures(new IncludesFeature(), new RegionLoader());

            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('nonexistent.yaml');

            $builder->build([
                'loader' => [
                    'yaml' => $yaml,
                    'array' => [
                        'includes' => [
                            'basePath' => $tempDir,
                        ],
                    ],
                ],
            ]);
        } finally {
            rmdir($tempDir);
        }
    }

    public function testIncludeRelativeThrowsExceptionWhenFileNotFound(): void
    {
        $tempDir = sys_get_temp_dir() . '/test_' . uniqid();
        mkdir($tempDir);

        try {
            $yaml = "data: !includeRelative nonexistent.yaml";

            $builder = new RegionBuilder();
            $builder->enableFeatures(new IncludesFeature(), new RegionLoader());

            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('nonexistent.yaml');

            $builder->build([
                'loader' => [
                    'yaml' => $yaml,
                    'array' => [
                        'includes' => [
                            'basePath' => $tempDir,
                        ],
                    ],
                ],
            ]);
        } finally {
            rmdir($tempDir);
        }
    }
}
