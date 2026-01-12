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
class SummonUsesHolonTest extends TestCase
{
    #[Test]
    public function summonLoadsCompleteHolonMachine(): void
    {
        // Create a Holon machine definition with features and container
        $testYaml = sys_get_temp_dir() . '/test-holon.yml';
        $yaml = <<<YAML
machine:
  features:
    - class: Noem\State\Feature\ExtendedState\ExtendedState
  container:
    services:
      testService:
        value: "test value"

states:
  - name: idle
    initial: true
    final: true
YAML;
        file_put_contents($testYaml, $yaml);

        try {
            $summonedRegion = null;

            $region = (new RegionBuilder())
                ->enableFeatures(new RegionLoader(), new ExtendedState())
                ->setStates('parent', 'done')
                ->markInitial('parent')
                ->markFinal('done')
                ->onEnter('parent', function (object $t) use ($testYaml, &$summonedRegion) {
                    // summon() should load via Holon::fromYaml(), which processes features/container
                    $summonedRegion = $this->summon($testYaml);
                    return 'done';
                })
                ->build();

            $region->trigger((object)[]);

            // Verify region was loaded
            $this->assertNotNull($summonedRegion, 'summon() should return loaded region');
            $this->assertInstanceOf(\Noem\State\Region::class, $summonedRegion);
        } finally {
            if (file_exists($testYaml)) {
                unlink($testYaml);
            }
        }
    }
}
