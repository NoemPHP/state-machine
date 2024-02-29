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
            return true;
          };
  - name: two
    onEnter: 
      # language=injectablephp
      - run: !php |
          return function(object $trigger){
            $this->set('message', 'hello');
          };
    regions:
     - inherits:
        - message  
       states:
        - name: one_one
          transitions:
            - target: one_two
        - name: one_two
          action:
            - run: !php |
                return function(object $trigger){
                  $message = $this->get('message');
                  $this->set('message', $message.' world');
                };
          transitions:
            - target: one_three
        - name: one_three
    transitions:
      - target: three
  - name: three
initial: one
final: three

YAML;
        $loader = RegionLoader::fromYaml($yaml);
        $region = $loader->build();
        while (!$region->isFinal()) {
            $region->trigger((object)['foo' => 'bar']);
        }
        $message=$region->getRegionContext('message');
        $this->assertSame($message,'hello world');
    }
}
