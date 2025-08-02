<?php

namespace Noem\State\Feature\Async;

use Closure;

class Call
{
    protected $callback;

    public function __construct(callable $callback)
    {
        $this->callback = $callback;
    }

    public function __invoke(Task $task, CoroutineScheduler $scheduler)
    {
        return ($this->callback)($task, $scheduler);
    }

    public static function waitForSecs(int $seconds): Call
    {
        return new Call(
            function (Task $task, CoroutineScheduler $scheduler) use ($seconds) {
                $scheduler->pause($task);
                $event = (object)['waitForSecs' => $seconds];
                $coroutine = function () use ($event, $scheduler, $task) {
                    $start = time();
                    $elapsed = 0;
                    while ($event->waitForSecs > $elapsed) {
                        $elapsed += time() - $start;
                        yield;
                    }
                    $scheduler->resume($task);
                };
                $scheduler->enqueue(
                    $coroutine()
                );
            }
        );
    }

    public static function take(string|Closure $fqcnOrMatcher, mixed $carry): Call
    {
        return self::takeAny($fqcnOrMatcher, $carry);
    }

    public static function takeAny(mixed $carry, string|Closure ...$eventMatchers): Call
    {
        return new Call(
            function (Task $task, CoroutineScheduler $scheduler) use ($eventMatchers) {
                $deregisterFuncs = [];
                foreach ($eventMatchers as $eventFQCN => $matcher) {
                    $timeout = 20;
                    $scheduler->pause($task);
                    $start = time();
                    $deregisterFuncs[] = $scheduler->listenerProvider()->addListener(
                        function (object $event) use (
                            $start,
                            $timeout,
                            $scheduler,
                            $task,
                            $eventFQCN,
                            $matcher,
                            &$deregisterFuncs
                        ) {
                            $now = time();
                            if (($now - $start) > $timeout) {
                                array_walk($deregisterFuncs, fn($f) => $f());
                            }
                            if (!$event instanceof $eventFQCN) {
                                return;
                            }
                            if (!$matcher($event)) {
                                return;
                            }
                            $scheduler->resume($task);
                            $task->setSendValue($event);
                            array_walk($deregisterFuncs, fn($f) => $f());
                        }
                    );
                }
            }
        );
    }

    /**
     * @param callable|\Generator $generatorFunc
     * @param mixed $buffer
     *
     * @return Call
     */
    public static function call(callable|\Generator $generatorFunc, mixed &$buffer = []): Call
    {
        if (is_array($generatorFunc)) {
            throw new \TypeError('we only accept objects');
        }
        assert(is_object($generatorFunc));

        return new Call(
            function (Task $task, CoroutineScheduler $scheduler) use ($generatorFunc, &$buffer) {
                $scheduler->pause($task);
                $generatorFunc = $generatorFunc instanceof \Generator
                    ? $generatorFunc
                    : $generatorFunc();
                $wrapped = function () use ($generatorFunc, &$buffer) {
                    foreach ($generatorFunc as $item) {
                        $buffer[] = $item;
                        yield $item;
                    }
                    return $generatorFunc->getReturn();
                };
                $newTask = $scheduler->enqueue($wrapped());
                $task->setSendValue($newTask);
                $scheduler->onComplete(
                    $newTask,
                    function () use ($task, $scheduler, $newTask) {
                        $return = $newTask->getReturn();
                        $scheduler->resume($task);
                        $task->setSendValue($return);
                    }
                );
            }
        );
    }

    public static function fork(\Closure $generatorFunc): Call
    {
        return new Call(
            function (Task $task, CoroutineScheduler $scheduler) use ($generatorFunc) {
                $forked = $scheduler->enqueue($generatorFunc(), spl_object_id($generatorFunc));
                $task->setSendValue($forked);
            }
        );
    }

    /**
     * Play russian roulette against the scheduler. If you lose, your current Task is killed,
     * but you may enqueue a new one as your $dare
     *
     * Jokes aside, this lets you specify which events can immediately abort your Task without blocking it.
     *
     * @param string $eventFQCN
     * @param callable|null $matcher
     * @param callable|null $dare
     *
     * @return Call
     */
    public static function wager(string $eventFQCN, ?callable $matcher = null, ?callable $dare = null): Call
    {
        $matcher = $matcher ?? fn() => true;

        return new Call(
            function (Task $task, CoroutineScheduler $scheduler) use ($eventFQCN, $matcher, $dare) {
                $deregisterFunc = $scheduler->listenerProvider()->addListener(
                    function (object $event) use (
                        $scheduler,
                        $task,
                        $eventFQCN,
                        $matcher,
                        $dare,
                        &$deregisterFunc
                    ) {
                        if (!$event instanceof $eventFQCN) {
                            return;
                        }
                        if (!$matcher($event)) {
                            return;
                        }
                        $scheduler->cancel($task);
                        if ($dare) {
                            $scheduler->enqueue($dare($event));
                        }
                    }
                );
                $scheduler->onComplete(
                    $task,
                    function () use ($deregisterFunc) {
                        $deregisterFunc();
                    }
                );
            }
        );
    }

    public static function resolveMeta(string $key): mixed
    {
        return new Call(function () {
        });
    }

    public static function cancel(Task $task): Call
    {
        return new Call(
            function (Task $__currentTask, CoroutineScheduler $scheduler) use ($task) {
                $scheduler->cancel($task);
            }
        );
    }
}
