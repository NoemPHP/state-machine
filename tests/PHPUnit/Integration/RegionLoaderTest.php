<?php

namespace Noem\State\Test\Integration;

use Mockery\Adapter\Phpunit\MockeryTestCase;
use Noem\State\RegionLoader;

class RegionLoaderTest extends MockeryTestCase
{

    /**
     * @test
     * @return void
     */
    public function fromYam()
    {
        // language=yaml
        $yaml = <<<'YAML'
states:
  - name: one
    transitions:
      - target: two
        # language=injectablephp
        guard: !php |
          return function(object $trigger): bool{
            echo "I am here";
            return true;
          };
  - name: two
    onEnter: 
      # language=injectablephp
      - callback: !php |
          return function(object $trigger){
            
          };
    regions:
     - states:
        - name: one_one
        - name: one_two
        - name: one_three
  - name: three
initial: one
final: three

YAML;
        $loader = RegionLoader::fromYaml($yaml);
        $region = $loader->build();
        $region->trigger((object)['foo' => 'bar']);
    }
}
