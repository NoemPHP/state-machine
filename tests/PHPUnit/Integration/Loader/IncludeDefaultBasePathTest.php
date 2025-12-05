<?php

declare(strict_types=1);

namespace Noem\State\Tests\Integration\Loader;

use Noem\State\Feature\Includes\IncludesFeature;
use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: !include defaults to current working directory when basePath not configured
 */
#[Group('loader')]
#[Group('yaml-include-helpers')]
class IncludeDefaultBasePathTest extends TestCase
{
    public function testIncludeDefaultsToCwdWhenBasePathNotConfigured(): void
    {
        $cwd = getcwd();
        $includedFile = $cwd . '/test_include_' . uniqid() . '.yaml';
        file_put_contents($includedFile, <<<YAML
- name: cwdState
YAML);

        try {
            $yaml = "states: !include " . basename($includedFile);

            $builder = new RegionBuilder();
            $builder->enableFeatures(new IncludesFeature(), new RegionLoader());

            // No basePath configured - should default to getcwd()
            $result = $builder->build([
                'loader' => [
                    'yaml' => $yaml,
                ],
            ]);

            $this->assertInstanceOf(\Noem\State\Region::class, $result);
        } finally {
            unlink($includedFile);
        }
    }
}
