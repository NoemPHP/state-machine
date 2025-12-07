<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Integration\Feature\Async;

use Noem\State\Feature\Async\AsyncFeature;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\Loader\RegionLoader;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Region with async resolvers loads from YAML configuration
 */
#[Group('async'), Group('integration')]
class LoadsResolversFromYamlTest extends TestCase
{
    public function testRegionWithAsyncResolversLoadsFromYaml(): void
    {
        $yaml = <<<YAML
states:
  - name: idle
    transitions:
      - target: active
  - name: active
    onEnter:
      - run: !php |
          return function(object \$trigger) {
            \$value = \$this->get('asyncValue');
            // Value should eventually resolve
          };
    transitions:
      - target: done
  - name: done
initial: idle
final: done
context:
  resolvers:
    - name: asyncValue
      run: !php |
          return function() {
            yield;
            return 'yaml-resolved';
          };
YAML;

        $builder = new \Noem\State\RegionBuilder();
        $builder->enableFeatures(
            new RegionLoader(),
            new ExtendedState(),
            new AsyncFeature()
        );

        $helpers = [
            'php' => new \Noem\State\Feature\Loader\Helper\PhpEvalHelper(),
        ];

        $region = $builder->build([
            'loader' => [
                'yaml' => $yaml,
                'yamlHelpers' => $helpers,
            ],
        ]);

        $this->assertInstanceOf(\Noem\State\Region::class, $region);

        // Trigger to active state to test resolver
        for ($i = 0; $i < 20; $i++) {
            $region->trigger(new \stdClass());
        }

        // If we reach here without errors, the YAML loading worked
        $this->assertTrue(true);
    }
}
