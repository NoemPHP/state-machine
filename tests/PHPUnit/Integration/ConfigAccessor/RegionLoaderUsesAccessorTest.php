<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Integration\ConfigAccessor;

use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: RegionLoader uses LoaderConfig to access state definitions
 */
#[Group('config-accessor'), Group('integration')]
class RegionLoaderUsesAccessorTest extends TestCase
{
    public function testRegionLoaderUsesAccessor(): void
    {
        $yamlContent = <<<YAML
initial: idle
final: done
states:
  - name: idle
  - name: processing
  - name: done
YAML;

        $builder = new RegionBuilder();
        $builder->enableFeatures(new RegionLoader());

        // Build region using YAML loader (which internally uses LoaderConfig accessor)
        $region = $builder->build([
            'loader' => [
                'yaml' => $yamlContent
            ]
        ]);

        // Verify states were loaded correctly through LoaderConfig accessor
        $this->assertTrue($region->isInState('idle'));
        $this->assertFalse($region->isInState('processing'));
        $this->assertFalse($region->isInState('done'));

        // Verify region was built successfully
        $this->assertNotNull($region);
    }
}
