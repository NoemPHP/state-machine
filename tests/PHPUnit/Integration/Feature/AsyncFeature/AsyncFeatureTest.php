<?php

declare(strict_types=1);

namespace Noem\State\Test\Integration\Feature\AsyncFeature;

use Noem\State\Chains\InvokeCallback;
use Noem\State\Chains\Meta;
use Noem\State\Chains\Params\Callback;
use Noem\State\Feature\Async\AsyncFeature;
use Noem\State\Feature\Async\Call;
use Noem\State\Feature\Async\IO\Fetch;
use Noem\State\Feature\Template\Compiler\TemplateFactory;
use Noem\State\Feature\Template\Helpers;
use Noem\State\Middleware\ChainMail;
use Noem\State\Region;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AsyncFeatureTest extends TestCase
{

    protected ChainMail $chainmail;

    protected InvokeCallback $invokeCallback;

    public function setUp(): void
    {
        $this->invokeCallback = new InvokeCallback();
        $meta = \Mockery::mock(Meta::class);
        $meta->allows('link')->andReturn($meta);

        $this->chainmail = new ChainMail();
        $this->chainmail->supply(
            fn(): InvokeCallback => $this->invokeCallback,
            fn(): Meta => $meta
        );
        $async = new AsyncFeature()($this->chainmail);
    }

    public function testCoroutines()
    {
        $region = \Mockery::mock(Region::class);
        $coroutine = function () {
            yield 'one';
            yield 'two';
        };
        $callback = new Callback($region, $coroutine, $region);
        $result = $this->invokeCallback->call($callback);
        $this->assertSame('one', $result);
        $result = $this->invokeCallback->call($callback);
        $this->assertSame('two', $result);
    }

    public function testWaitForSecs()
    {
        $region = \Mockery::mock(Region::class);

        $coroutine = function () {
            yield 'waiting';
            yield Call::waitForSecs(1);

            return 'done';
        };

        $callback = new Callback($region, $coroutine, $region);

        $result = $this->invokeCallback->call($callback);
        $this->assertSame('waiting', $result);
        /**
         * The next call will make it yield the Call::waitForSecs
         */
        $this->invokeCallback->call($callback);
        /**
         * So now the coroutine should be paused and the waitForSecs
         * is ticking
         */
        $this->invokeCallback->call($callback);
        usleep((int)(0.25 * 1000000));
        /**
         * The coroutine should still be waiting for 0.75 seconds
         *   because the waitForSecs is ticking, and we have only slept
         *   for 0.25 seconds.
         */
        $result = $this->invokeCallback->call($callback);
        $this->assertSame(
            'waiting',
            $result,
            'The coroutine should still be waiting for 0.75 seconds'
        );
        usleep((int)(0.76 * 1000000));
        /**
         * The subroutine must be able to write down the current time once
         */
        $result = $this->invokeCallback->call($callback);
        $this->assertSame('waiting', $result);

        /**
         * The subroutine will escape the waiting loop
         * Now the coroutine should be unpaused
         */
        $result = $this->invokeCallback->call($callback);
        $this->assertSame('waiting', $result);

        /**
         * The coroutine should have resumed and returned 'done'
         * The waitForSecs subroutine should be cancelled
         */
        $result = $this->invokeCallback->call($callback);
        $this->assertSame('done', $result);
        /**
         * So this one should result in the task being re-enqueued
         */
        $result = $this->invokeCallback->call($callback);
        $this->assertSame('waiting', $result);
    }

    //public function testTake()
    //{
    //    $invokeCallback = new InvokeCallback();
    //    $chainmail = new ChainMail();
    //    $chainmail->supply(fn(): InvokeCallback => $invokeCallback);
    //    $async = new AsyncFeature()($chainmail);
    //    $region = \Mockery::mock(Region::class);
    //
    //    $coroutine = function () {
    //        yield Call::take(
    //            \stdClass::class,
    //            fn($event) => $event->value === 'test',
    //            'waiting'
    //        );
    //
    //        return 'done';
    //    };
    //
    //    $callback = new Callback($region, $coroutine, $region);
    //    $invokeCallback->call($callback);
    //    $startTime = microtime(true);
    //    $endTime = $startTime + 2;
    //    while (microtime(true) < $endTime) {
    //        $result = $invokeCallback->call($callback);
    //    }
    //    $loop->addTimer(0.1, function () use ($invokeCallback, $region) {
    //        $event = (object)['value' => 'test'];
    //        $callback = new Callback($region, fn() => null, $region);
    //        $invokeCallback->call($callback);
    //    });
    //
    //    $this->assertSame('done', $result);
    //}
    //
    //public function testTakeAny()
    //{
    //    $loop = Factory::create();
    //    $invokeCallback = new InvokeCallback();
    //    $chainmail = new ChainMail();
    //    $chainmail->supply(fn(): InvokeCallback => $invokeCallback);
    //    $async = new AsyncFeature()($chainmail);
    //    $region = \Mockery::mock(Region::class);
    //
    //    $coroutine = function () {
    //        yield Call::takeAny([
    //            \stdClass::class => fn($event) => $event->value === 'test',
    //            \SplObjectStorage::class => fn($event) => false,
    //        ]);
    //
    //        return 'done';
    //    };
    //
    //    $callback = new Callback($region, $coroutine, $region);
    //    $invokeCallback->call($callback);
    //
    //    $loop->addTimer(0.1, function () use ($invokeCallback, $region) {
    //        $event = (object)['value' => 'test'];
    //        $callback = new Callback($region, fn() => null, $region);
    //        $invokeCallback->call($callback);
    //    });
    //
    //    $result = $loop->run();
    //
    //    $this->assertSame('done', $result);
    //}

    public function testCall()
    {
        $region = \Mockery::mock(Region::class);

        $coroutine = function () {
            yield Call::call(function () {
                yield 'subroutine';

                return 'done';
            });

            return 'main done';
        };

        $callback = new Callback($region, $coroutine, $region);
        $result = '';
        $result .= $this->invokeCallback->call($callback);
        $result .= $this->invokeCallback->call($callback);
        $result .= $this->invokeCallback->call($callback);
        $result .= $this->invokeCallback->call($callback);

        $this->assertSame('main done', $result);
    }

    public function testFetch()
    {

        $region = \Mockery::mock(Region::class);

        $coroutine = function () {
            yield Call::call(new Fetch('https://jsonplaceholder.typicode.com/posts/1'));

            return 'main done';
        };

        $callback = new Callback($region, $coroutine, $region);
        $result = '';

        $result .= $this->invokeCallback->call($callback);
        $result .= $this->invokeCallback->call($callback);
        usleep((int)(0.76 * 1000000));
        $result .= $this->invokeCallback->call($callback);
        usleep((int)(0.76 * 1000000));
        $result .= $this->invokeCallback->call($callback);
        //$result .= $invokeCallback->call($callback);
        //$result .= $invokeCallback->call($callback);
        //$result .= $invokeCallback->call($callback);
        //$result .= $invokeCallback->call($callback);
        //usleep((int)(0.76 * 1000000));
        //$result .= $invokeCallback->call($callback);
        //$result .= $invokeCallback->call($callback);
        //$result .= $invokeCallback->call($callback);
        //$result .= $invokeCallback->call($callback);
        //$result .= $invokeCallback->call($callback);
        //$result .= $invokeCallback->call($callback);

        $this->assertSame('main done', $result);
    }

    //public function testFork()
    //{
    //    $invokeCallback = new InvokeCallback();
    //    $chainmail = new ChainMail();
    //    $chainmail->supply(fn(): InvokeCallback => $invokeCallback);
    //    $async = new AsyncFeature()($chainmail);
    //    $region = \Mockery::mock(Region::class);
    //
    //    $coroutine = function () {
    //        yield Call::fork(function () {
    //            yield 'subroutine';
    //
    //            return 'done';
    //        });
    //
    //        return 'main done';
    //    };
    //
    //    $callback = new Callback($region, $coroutine, $region);
    //    $result = $invokeCallback->call($callback);
    //
    //    $this->assertSame('main done', $result);
    //}

    #[Test]
    public function templating()
    {
        $region = \Mockery::mock(Region::class);

        $coroutine = function () {
            yield 'Lorem ';
            $templateFactory = new TemplateFactory(new Helpers());
            $template = $templateFactory->create('ipsum {{thing}} sit');
            yield from $template(['thing' => 'dolor']);

            return ' amet';
        };

        $callback = new Callback($region, $coroutine, $region);
        $result = '';
        for ($i = 0; $i < 7; $i++) {
            $result .= $this->invokeCallback->call($callback);
        }

        $this->assertSame('Lorem ipsum dolor sit amet', $result);
    }

    //public function testWager()
    //{
    //    $invokeCallback = new InvokeCallback();
    //    $chainmail = new ChainMail();
    //    $chainmail->supply(fn(): InvokeCallback => $invokeCallback);
    //    $async = new AsyncFeature()($chainmail);
    //    $region = \Mockery::mock(Region::class);
    //
    //    $coroutine = function () {
    //        yield Call::wager(\stdClass::class, fn($event) => $event->value === 'abort', fn() => (function () {
    //            return 'aborted';
    //        })());
    //
    //        return 'done';
    //    };
    //
    //    $callback = new Callback($region, $coroutine, $region);
    //    $invokeCallback->call($callback);
    //
    //    $loop->addTimer(0.1, function () use ($invokeCallback, $region) {
    //        $event = (object)['value' => 'abort'];
    //        $callback = new Callback($region, fn() => null, $region);
    //        $invokeCallback->call($callback);
    //    });
    //
    //    $result = $loop->run();
    //
    //    $this->assertSame('aborted', $result);
    //}
}
