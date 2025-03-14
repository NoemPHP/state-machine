<?php

namespace Noem\State\Test\Integration;

use Mockery\Adapter\Phpunit\MockeryTestCase;
use Noem\State\Chains\Params\Get;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\Loader\Helper\ContainerGetHelper;
use Noem\State\Feature\Loader\Helper\PhpEvalHelper;
use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\Feature\OrthogonalRegions\OrthogonalRegions;
use Noem\State\Middleware\ChainException;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

class RegionLoaderTest extends RegionBuilderTestCase
{

    /**
     * @return void
     * @throws ChainException
     */
    #[Test]
    #[TestDox('It creates a working state machine from a yaml string')]
    public function fromYaml()
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
        $helpers = [
            'php' => new PhpEvalHelper(),
            'get' => new ContainerGetHelper($this->createContainer([
                'onEnterOneTwo' => function (object $t) use ($spy) {
                    $spy();
                },
            ])),
        ];
        $loaderFeature = new RegionLoader()->withYamlSupport($yaml, $helpers);
        $this->builder->enableFeatures(
            $loaderFeature,
            new ExtendedState(),
            new OrthogonalRegions()
        );
        $region = $this->builder->build();
        while (!$region->isFinal()) {
            $region->trigger((object)['foo' => 'bar']);
        }

        $this->assertRegionContext($region, 'message', 'hello world');
        $spy->shouldHaveBeenCalled()->once();
    }

    private function createContainer(array $data)
    {
        return new class ($data) implements ContainerInterface {

            public function __construct(private array $data)
            {
            }

            public function get(string $id)
            {
                if (!$this->has($id)) {
                    throw new class ("ID {$id} not found in container") extends \Exception implements
                        NotFoundExceptionInterface {

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
