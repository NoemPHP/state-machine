<?php

declare(strict_types=1);

namespace Noem\State;

use Noem\State\State\SimpleState;
use Noem\State\StateInterface;
use Noem\State\Transition\EventEnabledTransition;
use Noem\State\Transition\GuardedTransition;
use Noem\State\Transition\SimpleTransition;
use Noem\State\Transition\TransitionInterface;
use Noem\State\Util\ParameterDeriver;

class Transition
{
    public static function create(string|StateInterface $from, string|StateInterface $to): TransitionInterface
    {
        $from = is_string($from)
            ? new SimpleState($from)
            : $from;
        $to = is_string($to)
            ? new SimpleState($to)
            : $to;

        return new SimpleTransition($from, $to);
    }

    public static function forEvent(
        string|StateInterface $from,
        string|StateInterface $to,
        string $eventName
    ): TransitionInterface {
        return new EventEnabledTransition(
            self::create($from, $to),
            $eventName
        );
    }

    public static function forGuard(
        string|StateInterface $from,
        string|StateInterface $to,
        callable $guard
    ): TransitionInterface {
        $event = ParameterDeriver::getParameterType($guard);

        return new GuardedTransition(
            self::forEvent($from, $to, $event),
            $guard
        );
    }
}
