<?php

declare(strict_types=1);

namespace Noem\State\Test\Integration;

use Mockery\Adapter\Phpunit\MockeryTestCase;
use Noem\State\RegionBuilder;
use Noem\State\Region;

class RegionTest extends MockeryTestCase
{

    /**
     * @test
     * @return void
     */
    public function basicTransition()
    {
        //$this->markTestSkipped();
        $enterSpy = \Mockery::spy(fn() => true);
        $exitSpy = \Mockery::spy(fn() => true);

        $guardSpy = \Mockery::spy(fn() => true);

        $r = (new RegionBuilder())
            ->setStates(['one', 'two'])
            ->onExit('one', fn(object $t) => $exitSpy())
            ->onEnter('two', fn(object $t) => $enterSpy())
            ->markInitial('one')
            ->pushTransition('one', 'two', fn(object $t): bool => $guardSpy())
            ->build();
        $r->trigger((object)['foo' => 1]);
        $this->assertTrue($r->isInState('two'));
        $guardSpy->shouldHaveBeenCalled()->once();
        $exitSpy->shouldHaveBeenCalled()->once();
        $enterSpy->shouldHaveBeenCalled()->once();
    }

    /**
     * @test
     * @return void
     */
    public function exceptionHandling()
    {
        $r = (new RegionBuilder())
            ->setStates(['one', 'two', 'error'])
            ->onEnter('two', function (object $t) {
                throw new \Exception('Boo!');
            })
            ->markInitial('one')
            ->pushTransition('one', 'two', fn(object $t): bool => true)
            ->pushTransition('two', 'error', fn(\Throwable $e): bool => true)
            ->build();
        $r->trigger((object)['foo' => 1]);
        $this->assertTrue($r->isInState('error'));
    }

    /**
     * @test
     * @return void
     */
    public function basicSubRegion()
    {
        //$this->markTestSkipped();

        $handler = \Mockery::spy(fn() => true);

        $r = (new RegionBuilder())
            ->setStates(['one', 'two'])
            ->markInitial('one')
            ->pushTransition(
                'one', 'two',
                fn(object $t): bool => true
            )
            ->addRegion(
                'one',
                (new RegionBuilder)->setStates(['foo','bar'])
                    ->onAction('foo', function (object $t) use ($handler) {
                        $handler();
                    })->markFinal('foo')
            );
        $region = $r->build();
        $region->trigger((object)['foo' => 1]);

        $handler->shouldHaveBeenCalled()->once();
        $this->assertTrue($region->isInState('two'));
    }

    /**
     * @test
     * @return void
     */
    public function getStateContext()
    {
        $test = null;
        $r = new RegionBuilder();
        $r->setStates(['one','two'])
            ->addRegion(
                'one',
                (new RegionBuilder())
                    ->setStates(['foo','bar'])
                    ->inherits(['key'])
                    ->onAction('foo', function (object $t) use (&$test) {
                        $test = $this->key;
                    })
                    ->setStateContext('foo', [
                        'key' => 'value',
                    ])
            );

        $r->build()->trigger((object)['foo' => 1]);
        $this->assertSame($test, 'value');
    }

    /**
     * @test
     * @return void
     */
    public function nestedRegionContext()
    {
        $test = null;
        $subRegion = (new RegionBuilder())
            ->setStates(['foo','bar'])
            ->onAction('foo', function (object $t) use (&$test) {
                $test = $this->get('key');
            })
            ->setRegionContext([
                'key' => 'value',
            ]);
        $r = new RegionBuilder();
        $r->setStates(['one','two'])
            ->addRegion('one', $subRegion);

        $r->build()->trigger((object)['foo' => 1]);
        $this->assertSame($test, 'value');
    }

    /**
     * @test
     * @return void
     */
    public function getInheritedRegionContext()
    {
        $test = null;
        $r = new RegionBuilder();
        $r->setStates(['one','two'])
            ->addRegion(
                'one',
                (new RegionBuilder())
                    ->setStates(['foo','bar'])
                    ->inherits(['key'])
                    ->onAction('foo', function (object $t) use (&$test) {
                        $test = $this->get('key');
                    })
            )
            ->setRegionContext([
                'key' => 'value',
            ]);

        $r->build()->trigger((object)['foo' => 1]);
        $this->assertSame($test, 'value');
    }

    /**
     * @test
     * @return void
     */
    public function setInheritedRegionContext()
    {
        $subRegionBuilder = (new RegionBuilder())
            ->setStates(['foo','bar'])
            ->inherits(['key'])
            ->onAction('foo', function (object $t) use (&$test) {
                $this->set('key', 'newValue');
            });
        $r = new RegionBuilder();
        $r->setStates(['one','two'])
            ->addRegion('one', $subRegionBuilder)
            ->setRegionContext([
                'key' => 'value',
            ]);
        $region = $r->build();
        $region->trigger((object)['foo' => 1]);
        $subRegion = null;
        (function () use (&$subRegion) {
            assert($this instanceof Region);
            $privateRegionsProperty = 'regions'; // workaround to suppress IDE error
            $regions = $this->$privateRegionsProperty['one'];
            $subRegion = $regions[0];
        })->call($region);

        $this->assertSame($region->getRegionContext('key'), 'newValue');
        $this->assertSame($subRegion->getRegionContext('key'), null);
    }

    /**
     * @test
     * @return void
     */
    public function mutateInheritedRegionContext()
    {
        $subRegionBuilder = (new RegionBuilder())
            ->setStates(['foo','bar'])
            ->inherits(['key'])
            ->onAction('foo', function (object $t) use (&$test) {
                $key = $this->get('key');
                $this->set('key', $key.' world');
            });
        $r = new RegionBuilder();
        $r->setStates(['one','two'])
            ->addRegion('one', $subRegionBuilder)
            ->setRegionContext([
                'key' => 'hello',
            ]);
        $region = $r->build();
        $region->trigger((object)['foo' => 1]);
        $subRegion = null;
        (function () use (&$subRegion) {
            assert($this instanceof Region);
            $privateRegionsProperty = 'regions'; // workaround to suppress IDE error
            $regions = $this->$privateRegionsProperty['one'];
            $subRegion = $regions[0];
        })->call($region);

        $this->assertSame($region->getRegionContext('key'), 'hello world');
        $this->assertSame($subRegion->getRegionContext('key'), null);
    }

    /**
     * @test
     * @return void
     */
    public function simpleMiddleware()
    {
        $r = new RegionBuilder();
        $r->setStates(['one', 'two', 'three'])
            ->pushTransition('one', 'two')
            ->pushMiddleware(function (RegionBuilder $builder, \Closure $next) {
                $builder->pushTransition('two', 'three');

                return $next($builder);
            });
        $region = $r->build();
        $region->trigger((object)['foo' => 1]);
        $region->trigger((object)['foo' => 1]);
        $this->assertTrue($region->isInState('three'));
    }

    /**
     * @test
     * @return void
     */
    public function nestedLoggingMiddleware()
    {
        $logs = [];

        $middleware = function (RegionBuilder $builder, \Closure $next) use (&$logs) {
            $builder->eachState(function (string $s) use ($builder, &$logs) {
                $builder->onEnter($s, function (object $trigger) use ($s, &$logs) {
                    $logs[] = "ENTER: $s";
                });
                $builder->onExit($s, function (object $trigger) use ($s, &$logs) {
                    $logs[] = "EXIT: $s";
                });
            });

            return $next($builder);
        };

        $subRegion = (new RegionBuilder())
            ->setStates(['foo', 'bar'])
            ->pushTransition('foo', 'bar')
            ->pushMiddleware($middleware);


        $r = new RegionBuilder();
        $r->setStates(['one', 'two'])
            ->pushTransition('one', 'two')
            ->addRegion('two', $subRegion)
            ->pushMiddleware($middleware);

        $region = $r->build();
        $region->trigger((object)['foo' => 1]);
        $region->trigger((object)['foo' => 1]);

        //var_dump($logs);

        $this->assertSame(6, count($logs));
        $this->assertTrue($region->isInState('two'));
    }
}
