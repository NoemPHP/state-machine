<?php

declare(strict_types=1);

namespace Noem\State\Test\Integration;

use Mockery\Adapter\Phpunit\MockeryTestCase;
use Noem\State\Region;

class RegionTest extends MockeryTestCase
{

    /**
     * @test
     * @return void
     */
    public function basicTransition()
    {
        $r = new Region(['one', 'two'], 'one');
        $r->pushTransition('one', 'two', fn(object $t) => true);
        $r->trigger((object)['foo' => 1]);
        $this->assertTrue($r->isInState('two'));
    }

    /**
     * @test
     * @return void
     */
    public function basicSubRegion()
    {
        $r = new Region(['one', 'two'], 'one');
        $r->pushTransition('one', 'two', fn(object $t) => true);
        $handler = \Mockery::spy(fn()=>true);
        $r->pushRegion(
            'one',
            (new Region(['foo']))->onAction('foo', function (object $t) use ($handler) {
                $handler();
            })
        );
        $r->trigger((object)['foo' => 1]);

        $handler->shouldHaveBeenCalled()->once();
        $this->assertTrue($r->isInState('two'));
    }
}
