<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: RegionLoader uses empty helpers array when yamlHelpers not provided
 */
#[Group('loader')]
#[Group('builder-integration')]
class DefaultHelpersTest extends TestCase
{
    public function testUsesEmptyHelpersArrayWhenNotProvided(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new RegionLoader());

        $yaml = <<<YAML
        states:
          - name: idle
          - name: active
        initial: idle
        YAML;

        // Build without yamlHelpers - should use empty array by default
        $region = $builder->build([
            'loader' => [
                'yaml' => $yaml,
            ],
        ]);

        $this->assertTrue($region->isInState('idle'));
    }
}
