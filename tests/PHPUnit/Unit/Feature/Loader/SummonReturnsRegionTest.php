<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Loader;

use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\Region;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('loader')]
class SummonReturnsRegionTest extends TestCase
{
    #[Test]
    public function summonReturnsRegionInstance(): void
    {
        $testYaml = sys_get_temp_dir() . '/test-region.yml';
        file_put_contents($testYaml, "states:\n  - name: test\n    initial: true\n    final: true\n");

        try {
            $result = null;

            $region = (new RegionBuilder())
                ->enableFeatures(new RegionLoader(), new ExtendedState())
                ->setStates('idle', 'done')
                ->markInitial('idle')
                ->markFinal('done')
                ->onEnter('idle', function (object $t) use ($testYaml, &$result) {
                    $result = $this->summon($testYaml);
                    return 'done';
                })
                ->build();

            $region->trigger((object)[]);

            $this->assertInstanceOf(
                Region::class,
                $result,
                'summon() should return a Region instance'
            );
        } finally {
            if (file_exists($testYaml)) {
                unlink($testYaml);
            }
        }
    }
}
