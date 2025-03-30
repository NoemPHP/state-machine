<?php

declare(strict_types=1);

namespace Noem\State\Feature\Async\AsyncChains;

use Noem\State\Feature\Async\AsyncChains\Params\EnqueueParams;
use Noem\State\Feature\Async\CoroutineScheduler;
use Noem\State\Feature\Async\Task;
use Noem\State\Middleware\Chain;

/**
 * @template-extends Chain<Params\EnqueueParams,Task>
 */
class Enqueue extends Chain
{

    public function __construct(\SplObjectStorage $coroutinesByRegion)
    {
        parent::__construct(function (EnqueueParams $enqueueParams) use ($coroutinesByRegion) {
            $coroutine = $enqueueParams->coroutine;
            $scheduler = $coroutinesByRegion[$enqueueParams->region];
            assert($scheduler instanceof CoroutineScheduler);
            if (!$scheduler->contains($coroutine)) {
                $scheduler->enqueue($coroutine);
            }

            return $scheduler->getTaskForCoroutine($coroutine);
        });
    }
}
