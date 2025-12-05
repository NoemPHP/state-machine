<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\Feature\Includes\IncludesFeature;
use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\Feature\RequiresFeature;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: RegionLoader declares dependency on IncludesFeature via RequiresFeature attribute
 */
#[Group('loader')]
#[Group('yaml-include-helpers')]
class RequiresIncludesFeatureTest extends TestCase
{
    public function testRegionLoaderDeclaresIncludesFeatureDependency(): void
    {
        $reflection = new \ReflectionClass(RegionLoader::class);
        $attributes = $reflection->getAttributes(RequiresFeature::class);

        // Find the attribute that declares IncludesFeature dependency
        $includesFeatureDependency = null;
        foreach ($attributes as $attr) {
            $instance = $attr->newInstance();
            if ($instance->featureFQCN === IncludesFeature::class) {
                $includesFeatureDependency = $instance;
                break;
            }
        }

        $this->assertNotNull(
            $includesFeatureDependency,
            'RegionLoader should declare dependency on IncludesFeature via RequiresFeature attribute'
        );

        $this->assertSame(
            IncludesFeature::class,
            $includesFeatureDependency->featureFQCN,
            'RequiresFeature should reference IncludesFeature class'
        );
    }
}
