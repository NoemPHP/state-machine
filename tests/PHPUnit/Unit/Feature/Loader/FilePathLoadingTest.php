<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: RegionLoader loads YAML from file path when yaml arg is readable file
 */
#[Group('loader')]
#[Group('builder-integration')]
class FilePathLoadingTest extends TestCase
{
    public function testLoadsYamlFromFilePath(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new RegionLoader());
        
        // Create a temporary YAML file
        $tempFile = tempnam(sys_get_temp_dir(), 'yaml_test_');
        $yaml = <<<YAML
        states:
          - name: idle
          - name: active
        initial: idle
        YAML;
        file_put_contents($tempFile, $yaml);
        
        try {
            $region = $builder->build([
                'loader' => [
                    'yaml' => $tempFile,
                ]
            ]);
            
            $this->assertInstanceOf(\Noem\State\Region::class, $region);
            $this->assertTrue($region->isInState('idle'));
        } finally {
            unlink($tempFile);
        }
    }
}
