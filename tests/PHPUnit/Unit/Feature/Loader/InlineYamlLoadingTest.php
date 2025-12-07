<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: RegionLoader treats yaml arg as string when not a readable file
 */
#[Group('loader')]
#[Group('builder-integration')]
class InlineYamlLoadingTest extends TestCase
{
    public function testTreatsYamlArgAsStringWhenNotReadableFile(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new RegionLoader());

        $yaml = <<<YAML
        states:
          - name: idle
          - name: active
        initial: idle
        YAML;

        $region = $builder->build([
            'loader' => [
                'yaml' => $yaml,
            ]
        ]);

        $this->assertInstanceOf(\Noem\State\Region::class, $region);
        $this->assertTrue($region->isInState('idle'));
    }
}
