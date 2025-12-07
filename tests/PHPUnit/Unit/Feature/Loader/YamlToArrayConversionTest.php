<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: RegionLoader converts yaml to array before processing
 */
#[Group('loader')]
#[Group('builder-integration')]
class YamlToArrayConversionTest extends TestCase
{
    public function testConvertsYamlToArrayBeforeProcessing(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new RegionLoader());

        $yaml = <<<YAML
        states:
          - name: one
            transitions:
              - target: two
          - name: two
        initial: one
        final: two
        YAML;

        $region = $builder->build([
            'loader' => [
                'yaml' => $yaml,
            ],
        ]);

        // If YAML was properly converted to array and processed, region should work correctly
        $this->assertTrue($region->isInState('one'));
        $this->assertFalse($region->isFinal());

        $region->trigger((object)['test' => 1]);

        $this->assertTrue($region->isInState('two'));
        $this->assertTrue($region->isFinal());
    }
}
