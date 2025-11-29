<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: RegionLoader uses yamlHelpers when converting YAML
 */
#[Group('loader')]
#[Group('builder-integration')]
class YamlHelpersUsageTest extends TestCase
{
    public function testUsesYamlHelpersWhenConvertingYaml(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new RegionLoader());
        
        $yaml = <<<YAML
        states:
          - name: !uppercase "idle"
          - name: active
        initial: !uppercase "idle"
        YAML;
        
        $helpers = [
            'uppercase' => fn(string $value) => strtoupper($value),
        ];
        
        $region = $builder->build([
            'loader' => [
                'yaml' => $yaml,
                'yamlHelpers' => $helpers,
            ],
        ]);
        
        // If helper was used, state name should be 'IDLE' not 'idle'
        $this->assertTrue($region->isInState('IDLE'));
    }
}
