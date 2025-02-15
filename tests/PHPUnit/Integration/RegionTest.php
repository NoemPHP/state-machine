<?php

declare(strict_types=1);

namespace Noem\State\Test\Integration;

use Mockery\Adapter\Phpunit\MockeryTestCase;
use Noem\State\After;
use Noem\State\Event;
use Noem\State\Name;
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
        $enterSpy = \Mockery::spy(fn() => true);
        $exitSpy = \Mockery::spy(fn() => true);
        $guardSpy = \Mockery::spy(fn() => true);

        $r = (new RegionBuilder())
            ->setStates('one', 'two')
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
            ->setStates('one', 'two', 'error')
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
            ->setStates('one', 'two')
            ->markInitial('one')
            ->pushTransition(
                'one',
                'two',
                fn(object $t): bool => true
            )
            ->addRegion(
                'one',
                (new RegionBuilder())->setStates('foo', 'bar')
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
        $r->setStates('one', 'two')
            ->addRegion(
                'one',
                (new RegionBuilder())
                    ->setStates('foo', 'bar')
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
            ->setStates('foo', 'bar')
            ->onAction('foo', function (object $t) use (&$test) {
                $test = $this->get('key');
            })
            ->setRegionContext([
                'key' => 'value',
            ]);
        $r = new RegionBuilder();
        $r->setStates('one', 'two')
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
        $r->setStates('one', 'two')
            ->addRegion(
                'one',
                (new RegionBuilder())
                    ->setStates('foo', 'bar')
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
            ->setStates('foo', 'bar')
            ->inherits(['key'])
            ->onAction('foo', function (object $t) use (&$test) {
                $this->set('key', 'newValue');
            });
        $r = new RegionBuilder();
        $r->setStates('one', 'two')
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
            ->setStates('foo', 'bar')
            ->inherits(['key'])
            ->onAction('foo', function (object $t) use (&$test) {
                $key = $this->get('key');
                $this->set('key', $key . ' world');
            });
        $r = new RegionBuilder();
        $r->setStates('one', 'two')
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
        $r->setStates('one', 'two', 'three')
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
            ->setStates('2_foo', '2_bar')
            ->pushTransition('2_foo', '2_bar');

        $r = new RegionBuilder();
        $r->setStates('1_one', '1_two')
            ->pushTransition('1_one', '1_two')
            ->addRegion('1_two', $subRegion)
            ->pushMiddleware($middleware);

        $region = $r->build();
        $region->trigger((object)['foo' => 1]);
        $region->trigger((object)['foo' => 1]);

        //var_dump($logs);

        $this->assertSame(5, count($logs));
        $this->assertTrue($region->isInState('1_two'));
    }

    /**
     * @test
     * @return void
     */
    public function nestedStateName()
    {
        $fqsn = '';
        $subRegion = (new RegionBuilder())
            ->setStates('two')
            ->onAction('two', function (object $t) use (&$fqsn) {
                $fqsn = (string)$this;
            });

        $r = new RegionBuilder();
        $r->setStates('one')
            ->addRegion('one', $subRegion);

        $region = $r->build();
        $region->trigger((object)['foo' => 1]);
        $this->assertSame('one.two', $fqsn);
    }

    /**
     * @test
     * @return void
     */
    public function eventChaining()
    {
        $this->markTestSkipped("I am worried about the infinite-loop-potential of dispatching immediately. This is disabled for now until I find a roadblock that forces me to have this functionality");
        $r = new RegionBuilder();
        $r->setStates('one', 'two', 'three')
            ->pushTransition('one', 'two')
            ->pushTransition('two', 'three')
            ->onEnter('two', function (object $trigger) {
                /** @noinspection PhpUndefinedMethodInspection */
                $this->dispatch((object)['hello' => 'world']);
            });
        $region = $r->build();
        $region->trigger((object)['foo' => 1]);
        $this->assertTrue($region->isInState('three'), "Region should be in state 'three'");
    }

    /**
     * @test
     * @return void
     */
    public function namedEvents()
    {
        $guardSpy = \Mockery::spy(fn() => true);

        $r = new RegionBuilder();
        $r->setStates('one', 'two', 'three')
            ->pushTransition('one', 'two', fn(#[Name('hello-world')] Event $t): bool => $guardSpy())
        ;
        $region = $r->build();
        $region->trigger((object)['foo' => 1]);
        $this->assertFalse($region->isInState('two'), "Region should ignore non-matching event'");

        $event = new class implements Event
        {
            public function name(): string
            {
                return 'hello-world';
            }
        };

        $region->trigger($event);

        $this->assertTrue($region->isInState('two'), "Region should be in state 'two'");
    }

    /**
     * @test
     * @return void
     */
    public function afterEvent()
    {
        $guardSpy = \Mockery::spy(fn() => true);
        $r = new RegionBuilder();
        $r->setStates('one', 'two', 'three')
            ->pushTransition('one', 'two', #[After] fn( Event $t): bool => $guardSpy());
        $region = $r->build();
        $region->trigger((object)['foo' => 1]);
        $this->assertFalse($region->isInState('two'), "Region should ignore non-matching event'");

        $event = new class implements Event {

            public function name(): string
            {
                return 'hello-world';
            }
        };

        $region->trigger($event);
        $guardSpy->shouldHaveBeenCalled()->once();
        $this->assertTrue($region->isInState('two'), "Region should be in state 'two'");
    }
}
