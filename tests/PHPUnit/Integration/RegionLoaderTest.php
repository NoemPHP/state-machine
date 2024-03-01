<?php

namespace Noem\State\Test\Integration;

use Mockery\Adapter\Phpunit\MockeryTestCase;
use Noem\State\Helper\ContainerGetHelper;
use Noem\State\Helper\PhpEvalHelper;
use Noem\State\RegionLoader;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

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
          onEnter:
            - run: !get onEnterOneTwo
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
        $spy = \Mockery::spy(fn() => true);
        $loader = (new RegionLoader(
            [
                'php' => new PhpEvalHelper(),
                'get' => new ContainerGetHelper($this->createContainer([
                    'onEnterOneTwo' => function (object $t) use ($spy) {
                        $spy();
                    },
                ])),
            ]
        ))->fromYaml($yaml);
        $region = $loader->build();
        while (!$region->isFinal()) {
            $region->trigger((object)['foo' => 'bar']);
        }
        $message = $region->getRegionContext('message');
        $this->assertSame($message, 'hello world');
        $spy->shouldHaveBeenCalled()->once();
    }

    private function createContainer(array $data)
    {
        return new class($data) implements ContainerInterface {

            public function __construct(private array $data)
            {
            }

            public function get(string $id)
            {
                if (!$this->has($id)) {
                    throw new class("ID {$id} not found in container")
                        extends \Exception
                        implements NotFoundExceptionInterface {

                    };
                }

                return $this->data[$id];
            }

            public function has(string $id): bool
            {
                return isset($this->data[$id]);
            }
        };
    }
}
