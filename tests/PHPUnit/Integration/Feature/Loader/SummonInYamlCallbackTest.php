<?php

declare(strict_types=1);

namespace Noem\State\Test\Integration\Feature\Loader;

use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\Loader\Helper\PhpEvalHelper;
use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('loader')]
class SummonInYamlCallbackTest extends TestCase
{
    #[Test]
    public function summonWorksInYamlOnEnterCallback(): void
    {
        // Create child machine YAML
        $childYaml = sys_get_temp_dir() . '/child-machine.yml';
        $childContent = <<<YAML
states:
  - name: working
    onEnter:
      - run: !php |
          return function(\$t) {
            \$this->set('child_executed', true);
          };
  - name: done
initial: working
final: done
YAML;
        file_put_contents($childYaml, $childContent);

        // Create parent machine YAML that uses summon()
        $parentYaml = sys_get_temp_dir() . '/parent-machine.yml';
        $parentContent = <<<YAML
states:
  - name: parent_idle
    onEnter:
      - run: !php |
          return function(\$t) {
            \$child = \$this->summon('$childYaml');
            \$this->set('child_region', \$child);
            return 'parent_done';
          };
    transitions:
      - target: parent_done
  - name: parent_done
initial: parent_idle
final: parent_done
YAML;
        file_put_contents($parentYaml, $parentContent);

        try {
            $region = (new RegionBuilder())
                ->enableFeatures(new RegionLoader(), new ExtendedState())
                ->build([
                    'loader' => [
                        'yaml' => $parentYaml,
                        'yamlHelpers' => [
                            'php' => new PhpEvalHelper(),
                        ]
                    ]
                ]);

            $region->trigger((object)[]);

            // If we got here without exceptions, summon() worked in the YAML callback
            // The callback returned 'parent_done', which means it executed successfully
            $this->assertTrue(
                $region->isFinal(),
                'summon() should work in YAML callback and allow transition to final state'
            );
        } finally {
            if (file_exists($childYaml)) {
                unlink($childYaml);
            }
            if (file_exists($parentYaml)) {
                unlink($parentYaml);
            }
        }
    }
}
