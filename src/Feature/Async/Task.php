<?php

namespace Noem\State\Feature\Async;

class Task
{
    protected \Generator $coroutine;

    protected mixed $sendValue = null;

    protected bool $beforeFirstYield = true;

    public function __construct(\Generator $coroutine)
    {
        $this->coroutine = $coroutine;
    }

    public function setSendValue($sendValue)
    {
        $this->sendValue = $sendValue;
    }

    public function run()
    {
        if ($this->beforeFirstYield) {
            $this->beforeFirstYield = false;

            return $this->coroutine->current();
        } else {
            $retval = $this->coroutine->send($this->sendValue);
            if ($this->isFinished()) {
                return $this->coroutine->getReturn();
            }
            $this->sendValue = null;

            return $retval;
        }
    }

    public function getReturn(): mixed
    {
        return $this->coroutine->getReturn();
    }

    public function isFinished(): bool
    {
        return !$this->coroutine->valid();
    }
}
