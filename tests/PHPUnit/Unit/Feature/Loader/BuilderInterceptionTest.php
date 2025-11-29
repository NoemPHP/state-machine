<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: RegionLoader intercepts build process when loader args present
 */
#[Group('loader')]
#[Group('builder-integration')]
class BuilderInterceptionTest extends TestCase
{
    public function testInterceptsBuildProcessWhenLoaderArgsPresent(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new RegionLoader());
        
        $yaml = <<<YAML
        states:
          - name: idle
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
