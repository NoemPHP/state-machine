<?php

declare(strict_types=1);

namespace Noem\State\Test\Integration\Feature\AsyncFeature;

use Noem\State\Feature\Async\AsyncFeature;
use Noem\State\Feature\Async\Call;
use Noem\State\Feature\Async\IO\Fetch;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\Template\Compiler\TemplateFactory;
use Noem\State\Feature\Template\Helpers;
use Noem\State\Feature\Transitions\AddTransition;
use Noem\State\Feature\Transitions\TransitionsFeature;
use Noem\State\Test\Integration\RegionBuilderTestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

class AsyncFeatureTest extends RegionBuilderTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        $this->builder->enableFeatures(
            new AsyncFeature(),
            new TransitionsFeature()
        );
    }

    public function testCoroutines()
    {
        $region = $this->builder
            ->setStates('one', 'two')
            ->onAction('one', function (object $t) {
                $t->out .= '/two';
                yield;
                $t->out .= '/three';
                yield;
            })
            ->build();
        $payload = new \stdClass();
        $payload->out = '/one';
        $region->trigger($payload);
        $this->assertSame('/one/two', $payload->out);
        $region->trigger($payload);
        $this->assertSame('/one/two/three', $payload->out);
    }

    public function testWaitForSecs()
    {
        $region = $this->builder
            ->setStates('one', 'two')
            ->onAction('one', function (object $t) {
                $t->out .= '/waiting';
                yield;
                yield Call::waitForSecs(1);
                $t->out .= '/done';
                yield;
            })
            ->build();

        $payload = new \stdClass();
        $payload->out = '/start';
        $region->trigger($payload);
        $this->assertSame('/start/waiting', $payload->out);
        /**
         * The next call will make it yield the Call::waitForSecs
         */
        $region->trigger($payload);

        /**
         * So now the coroutine should be paused and the waitForSecs
         * is ticking
         */
        $region->trigger($payload);
        usleep((int)(0.25 * 1000000));
        /**
         * The coroutine should still be waiting for 0.75 seconds
         *   because the waitForSecs is ticking, and we have only slept
         *   for 0.25 seconds.
         */
        $region->trigger($payload);
        $this->assertSame(
            '/start/waiting',
            $payload->out,
            'The coroutine should still be waiting for 0.75 seconds'
        );
        usleep((int)(0.76 * 1000000));
        /**
         * The subroutine must be able to write down the current time once
         */
        $region->trigger($payload);

        $this->assertSame('/start/waiting', $payload->out);
        usleep((int)(0.05 * 1000000));

        /**
         * The subroutine will escape the waiting loop
         * Now the coroutine should be unpaused
         */
        $region->trigger($payload);

        $this->assertSame('/start/waiting', $payload->out);

        /**
         * The coroutine should have resumed and returned 'done'
         * The waitForSecs subroutine should be cancelled
         */
        $region->trigger($payload);

        $this->assertSame('/start/waiting/done', $payload->out);
        /**
         * So this one should result in the task being re-enqueued
         * TODO: Challenge whether this is even desired. For now it's not implemented
         */
        //$region->trigger($payload);
        //$this->assertSame('/start/waiting', $payload->out);
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
    #[Test]
    #[TestDox('It can resolve values transparently')]
    public function resolver()
    {
        $region = $this->builder
            /**
             * This feature is needed only within this test so we'll add it here
             */
            ->enableFeatures(
                new ExtendedState()
            )
            ->setStates('one', 'two')
            ->addBuildStep(new AddTransition('one', 'two', function (object $t): bool {
                return $this->get('context') !== null;
            }))
            ->onEnter('two', function (object $t) {
                $t->out .= $this->get('context');
            })
            ->build(
            /**
             * I swear to god this needs a sugar API
             */
                [
                    'loader' =>
                        [
                            'array' => [
                                'context' => [
                                    'resolvers' => [
                                        [
                                            'name' => 'context',
                                            'run' => function () {
                                                yield;

                                                return '/done';
                                            },
                                        ],
                                    ],
                                ],
                            ],
                        ],
                ]
            );

        $payload = new \stdClass();
        $payload->out = '/start';
        while (!$region->isFinal()) {
            $region->trigger($payload);
        }
        $this->assertSame('/start/done', $payload->out);
    }

    #[Test]
    #[TestDox('It can resolve values transparently')]
    public function resolveWithNestedContext()
    {
        $region = $this->builder
            /**
             * This feature is needed only within this test so we'll add it here
             */
            ->enableFeatures(
                new ExtendedState()
            )
            ->setStates('off', 'one', 'two')
            ->addBuildStep(new AddTransition('off', 'one'))
            ->addBuildStep(new AddTransition('one', 'two', function (object $t): bool {
                return $this->get('context') !== null;
            }))
            ->onEnter('one', function (object $t) {
                $this->set('dependency', 'waiting');
            })
            ->onEnter('two', function (object $t) {
                $t->out .= $this->get('context');
            })
            ->build(
            /**
             * I swear to god this needs a sugar API
             */
                [
                    'loader' =>
                        [
                            'array' => [
                                'context' => [
                                    'resolvers' => [
                                        [
                                            'name' => 'context',
                                            'run' => function () {
                                                yield;
                                                $dependency = $this->get('dependency');

                                                return "/{$dependency}/done";
                                            },
                                        ],
                                    ],
                                ],
                            ],
                        ],
                ]
            );

        $payload = new \stdClass();
        $payload->out = '/start';
        while (!$region->isFinal()) {
            $region->trigger($payload);
        }
        $this->assertSame('/start/waiting/done', $payload->out);
    }

    public function testContext()
    {
        $region = $this->builder
            /**
             * This feature is needed only within this test so we'll add it here
             */
            ->enableFeatures(
                new ExtendedState()
            )
            ->setStates('one', 'two')
            ->onAction('one', function (object $t) {
                $this->set('context', '');
                yield
                $this->set('context', '/done');
                yield;
                $t->out .= $this->get('context');
            })
            ->build();

        $payload = new \stdClass();
        $payload->out = '/start';
        $region->trigger($payload);
        $region->trigger($payload);
        $region->trigger($payload);
        $region->trigger($payload);
        $region->trigger($payload);
        $region->trigger($payload);

        $this->assertSame('/start/done', $payload->out);
    }

    public function testCall()
    {
        $region = $this->builder
            ->setStates('one', 'two')
            ->onAction('one', function (object $t) {
                $t->out .= '/one';
                yield;
                $result = yield Call::call(function () {
                    yield '/two';
                    yield '/three';

                    return '/four';
                }, $buffer);
                $t->out .= implode($buffer);
                $t->out .= $result;
                yield;
            })
            ->build();

        $payload = new \stdClass();
        $payload->out = '';
        /**
         * Run the machine for a bit...
         */
        $region->trigger($payload);
        $region->trigger($payload);
        $region->trigger($payload);
        $region->trigger($payload);
        $region->trigger($payload);
        $region->trigger($payload);

        $this->assertSame('/one/two/three/four', $payload->out);
    }

    public function testFetch()
    {
        $region = $this->builder
            ->setStates('one', 'two')
            ->onAction('one', function (object $t) {
                $responseHeaders = yield Call::call(
                    new Fetch('https://jsonplaceholder.typicode.com/posts/1'),
                    $response
                );
                $json = implode($response);
                $json = json_decode($json, true);
                $t->out .= $responseHeaders[0];
                $t->out .= '|user:' . $json['userId'];
                $t->out .= '|END';
                yield;
            })
            ->addBuildStep(new AddTransition('one', 'two', function (object $t): bool {
                return str_ends_with($t->out, 'END');
            }))
            ->build();

        $payload = new \stdClass();
        $payload->out = '';
        $region->trigger($payload);
        while (!$region->isFinal()) {
            $region->trigger($payload);
        }
        $this->assertSame('HTTP/1.1 200 OK|user:1|END', $payload->out);
    }

    #[Test]
    public function templating()
    {
        $region = $this->builder
            ->setStates('one', 'two')
            ->onAction('one', function (object $t) {
                $t->out .= 'Lorem ';
                $templateFactory = new TemplateFactory(new Helpers());
                $template = $templateFactory->create('ipsum {{thing}} sit');
                foreach ($template(['thing' => 'dolor']) as $chunk) {
                    $t->out .= $chunk;
                    yield;
                }
            })
            ->build();

        $payload = new \stdClass();
        $payload->out = '';
        $region->trigger($payload);
        $region->trigger($payload);
        $region->trigger($payload);
        $this->assertSame('Lorem ipsum dolor sit', $payload->out);
    }
}
