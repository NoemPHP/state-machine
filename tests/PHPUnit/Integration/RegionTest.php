<?php

declare(strict_types=1);

namespace Noem\State\Test\Integration;

use Mockery\Adapter\Phpunit\MockeryTestCase;
use Noem\State\RegionBuilder;

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
            ->onExit('one', fn() => $exitSpy())
            ->onEnter('two', fn() => $enterSpy())
            ->markInitial('one')
            ->pushTransition('one', 'two', fn(object $t) => $guardSpy())
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
    public function basicSubRegion()
    {
        //$this->markTestSkipped();

        $handler = \Mockery::spy(fn() => true);

        $r = (new RegionBuilder())
            ->setStates(['one', 'two'])
            ->markInitial('one')
            ->pushTransition(
                'one', 'two',
                fn(object $t) => true
            )
            ->addRegion(
                'one',
                (new RegionBuilder)->setStates(['foo'])
                    ->onAction('foo', function (object $t) use ($handler) {
                        $handler();
                    })->build()
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
        $r->setStates(['one'])
            ->addRegion(
                'one',
                (new RegionBuilder())
                    ->setStates(['foo'])
                    ->inherits(['key'])
                    ->onAction('foo', function (object $t) use (&$test) {
                        $test = $this->key;
                    })
                    ->setStateContext('foo', [
                        'key' => 'value',
                    ])
                    ->build()
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
        $r = new RegionBuilder();
        $r->setStates(['one'])
            ->addRegion(
                'one',
                (new RegionBuilder())
                    ->setStates(['foo'])
                    ->onAction('foo', function (object $t) use (&$test) {
                        $test = $this->get('key');
                    })
                    ->setRegionContext([
                        'key' => 'value',
                    ])
                    ->build()
            );

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
        $r->setStates(['one'])
            ->addRegion(
                'one',
                (new RegionBuilder())
                    ->setStates(['foo'])
                    ->inherits(['key'])
                    ->onAction('foo', function (object $t) use (&$test) {
                        $test = $this->get('key');
                    })->build()
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
        $r = new RegionBuilder();
        $r->setStates(['one'])
            ->addRegion(
                'one',
                (new RegionBuilder())
                    ->setStates(['foo'])
                    ->inherits(['key'])
                    ->onAction('foo', function (object $t) use (&$test) {
                        $this->set('key', 'newValue');
                    })->build()
            )
            ->setRegionContext([
                'key' => 'value',
            ]);
        $region = $r->build();
        $region->trigger((object)['foo' => 1]);
        $this->assertSame($region->getRegionContext('key'), 'newValue');
    }
}
