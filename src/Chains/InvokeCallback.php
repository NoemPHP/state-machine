<?php

declare(strict_types=1);

namespace Noem\State\Chains;

use Noem\State\Chains\Params\Callback;
use Noem\State\Middleware\Chain;

/**
 * @template-extends Chain<Callback,mixed>
 */
class InvokeCallback extends Chain
{

    public function __construct()
    {
        parent::__construct(function (Callback $context): mixed {
            return ($context->handler)($context->trigger);
        });
    }
}
