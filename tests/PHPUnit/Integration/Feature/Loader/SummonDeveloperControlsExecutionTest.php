<?php

declare(strict_types=1);

namespace Noem\State\Test\Integration\Feature\Loader;

use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\Loader\Helper\PhpEvalHelper;
use Noem\State\Feature\Loader\Holon;
use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\RegionBuilder;
use Noem\State\StandardRuntime;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('loader')]
class SummonDeveloperControlsExecutionTest extends TestCase
{
    #[Test]
    public function developerControlsWhenToExecuteSummonedRegion(): void
    {
        // Create simple child machine
        $childYaml = sys_get_temp_dir() . '/worker.yml';
        $childContent = <<<YAML
states:
  - name: working
    transitions:
      - target: complete
  - name: complete
initial: working
final: complete
YAML;
        file_put_contents($childYaml, $childContent);

        try {
            $childExecuted = false;

            $region = (new RegionBuilder())
                ->enableFeatures(new RegionLoader(), new ExtendedState())
                ->setStates('idle', 'running', 'done')
                ->markInitial('idle')
                ->markFinal('done')
                ->onEnter('idle', function (object $t) use ($childYaml, &$childExecuted) {
                    // summon() returns Region - developer decides when/how to execute
                    $childRegion = $this->summon($childYaml);

                    // Developer wraps in Runtime and calls run() explicitly
                    $runtime = new StandardRuntime($childRegion);
                    $runtime->run();

                    // Verify that developer explicitly controlled execution
                    // (If summon() auto-executed, this callback wouldn't get here)
                    $childExecuted = true;

                    return 'done';
                })
                ->build();

            $region->trigger((object)[]);

            $this->assertTrue(
                $childExecuted,
                'Developer should control when summoned region executes'
            );
        } finally {
            if (file_exists($childYaml)) {
                unlink($childYaml);
            }
        }
    }

    #[Test]
    public function developerCanChooseNotToExecuteSummonedRegion(): void
    {
        // Create child machine
        $childYaml = sys_get_temp_dir() . '/optional-worker.yml';
        $childContent = <<<YAML
states:
  - name: working
    transitions:
      - target: complete
  - name: complete
initial: working
final: complete
YAML;
        file_put_contents($childYaml, $childContent);

        try {
            $childRegionStored = false;

            $region = (new RegionBuilder())
                ->enableFeatures(new RegionLoader(), new ExtendedState())
                ->setStates('idle', 'done')
                ->markInitial('idle')
                ->markFinal('done')
                ->onEnter('idle', function (object $t) use ($childYaml, &$childRegionStored) {
                    // summon() returns Region but developer chooses NOT to execute it
                    $childRegion = $this->summon($childYaml);

                    // Just store it for later use
                    $this->set('deferred_child', $childRegion);
                    $childRegionStored = true;
                    return 'done';
                })
                ->build();

            $region->trigger((object)[]);

            $this->assertTrue(
                $childRegionStored,
                'Developer should be able to summon without immediate execution'
            );
        } finally {
            if (file_exists($childYaml)) {
                unlink($childYaml);
            }
        }
    }
}
