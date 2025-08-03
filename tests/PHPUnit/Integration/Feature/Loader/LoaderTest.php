<?php

declare(strict_types=1);

namespace Noem\State\Test\Integration\Feature\Loader;

use DateTime;
use Noem\State\Feature\Async\AsyncFeature;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\JsonSchema\JsonSchemaFeature;
use Noem\State\Feature\Loader\Helper\ContainerGetHelper;
use Noem\State\Feature\Loader\Helper\PhpEvalHelper;
use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\Feature\OrthogonalRegions\OrthogonalRegions;
use Noem\State\Middleware\ChainException;
use Noem\State\Test\Integration\RegionBuilderTestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

class LoaderTest extends RegionBuilderTestCase
{

    /**
     * @return void
     * @throws ChainException
     */
    #[Test]
    #[TestDox('It creates a working state machine from a yaml string')]
    public function basicYaml()
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
      - run: !get onEnterTwo
YAML;
        $spy = \Mockery::spy(fn() => true);
        $helpers = [
            'php' => new PhpEvalHelper(),
            'get' => new ContainerGetHelper($this->createContainer([
                'onEnterTwo' => function (object $t) use ($spy) {
                    $spy();
                },
            ])),
        ];
        $this->builder->enableFeatures(
            new RegionLoader(),
            new ExtendedState(),
            new OrthogonalRegions(),
            new AsyncFeature(),
            new JsonSchemaFeature()
        );
        $region = $this->builder->build([
            'loader' => [
                'yaml' => $yaml,
                'yamlHelpers' => $helpers,
            ],
        ]);
        $this->assertRegionContext($region, 'html', null);
        while (!$region->isFinal()) {
            $region->trigger((object)['foo' => 'bar']);
        }

        $spy->shouldHaveBeenCalled()->once();
    }

    /**
     * @return void
     * @throws ChainException
     */
    #[Test]
    #[TestDox('It spawns a working sub-machine')]
    public function spawnSubMachine()
    {
        // language=yaml
        $yaml = <<<'YAML'
states:
  - name: off
    transitions:
      - target: one
  - name: one
    onEnter:
      - run: !get onEnterOne
    spawn:
      # language=injectablephp
      - guard: !get spawnGuard
        region:
          states:
            - name: sub_one
              transitions:
              - target: sub_two
                # language=injectablephp
                guard: !php |
                  return function(object $trigger): bool{
                    return true;
                  }; 
            - name: sub_two
              onEnter:
                - run: !get onEnterSubTwo
    transitions:
      - target: two
        # language=injectablephp
        guard: !php |
          return function(object $trigger): bool{
            return true;
          };
  - name: two
    onEnter:
      - run: !get onEnterTwo
YAML;
        $spy = \Mockery::spy(fn() => true);
        $subSpy = \Mockery::spy(fn() => true);
        $helpers = [
            'php' => new PhpEvalHelper(),
            'get' => new ContainerGetHelper($this->createContainer([
                'spawnGuard' => function (DateTime $trigger): bool {
                    return true;
                },
                'onEnterOne' => function (object $t) use ($spy) {
                    $this->dispatch(new DateTime());
                },
                'onEnterTwo' => function (object $t) use ($spy) {
                    $spy();
                },
                'onEnterSubTwo' => function (object $t) use ($subSpy) {
                    $subSpy();
                },
            ])),
        ];
        $this->builder->enableFeatures(
            new RegionLoader(),
            new ExtendedState(),
            new OrthogonalRegions(),
            new AsyncFeature(),
            new JsonSchemaFeature()
        );
        $region = $this->builder->build([
            'loader' => [
                'yaml' => $yaml,
                'yamlHelpers' => $helpers,
            ],
        ]);
        $this->assertRegionContext($region, 'html', null);
        while (!$region->isFinal()) {
            $region->trigger((object)['foo' => 'bar']);
        }

        $spy->shouldHaveBeenCalled()->once();
        $subSpy->shouldHaveBeenCalled()->once();
    }

    /**
     * @return void
     * @throws ChainException
     */
    #[Test]
    #[TestDox('It creates a working state machine from a yaml string')]
    public function complexYaml()
    {
        $this->markTestSkipped('This machine makes no sense');
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
      - states:
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
context:
  schema:
    - name: message
      type: string
      default: Lorem ipsum
      description: The message to use by the machine
  resolvers:
    - name: html
      run: !php |
          return function(){
            yield;
            $message = $this->get('message');
            return '<div>' . $message . '</div>';
          }
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
        $this->builder->enableFeatures(
            new RegionLoader(),
            new ExtendedState(),
            new OrthogonalRegions(),
            new AsyncFeature(),
            new JsonSchemaFeature()
        );
        $region = $this->builder->build([
            'loader' => [
                'yaml' => $yaml,
                'yamlHelpers' => $helpers,
            ],
        ]);
        $this->assertRegionContext($region, 'html', null);
        while (!$region->isFinal()) {
            $region->trigger((object)['foo' => 'bar']);
        }

        $this->assertRegionContext($region, 'message', 'hello world');
        $this->assertRegionContext($region, 'html', '<div>hello</div>');
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
