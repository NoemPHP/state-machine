<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Loader;

use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('loader')]
class SummonHelperRegistrationTest extends TestCase
{
    #[Test]
    public function summonMethodIsAvailableInCallbacks(): void
    {
        $summonCalled = false;
        $childRegion = null;

        // Create a minimal test YAML file
        $testYaml = sys_get_temp_dir() . '/test-summon.yml';
        file_put_contents($testYaml, "states:\n  - name: test\n    initial: true\n    final: true\n");

        try {
            $region = (new RegionBuilder())
                ->enableFeatures(new RegionLoader(), new ExtendedState())
                ->setStates('idle', 'done')
                ->markInitial('idle')
                ->markFinal('done')
                ->onEnter('idle', function (object $t) use ($testYaml, &$summonCalled, &$childRegion) {
                    // Call summon() - if method doesn't exist, BoundAccess will throw RuntimeException
                    $childRegion = $this->summon($testYaml);
                    $summonCalled = true;
                    return 'done';
                })
                ->build();

            $region->trigger((object)[]);

            $this->assertTrue($summonCalled, 'summon() should be callable without throwing exception');
            $this->assertNotNull($childRegion, 'summon() should return a region');
        } finally {
            if (file_exists($testYaml)) {
                unlink($testYaml);
            }
        }
    }
}
