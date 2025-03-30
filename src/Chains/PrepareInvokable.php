<?php

declare(strict_types=1);

namespace Noem\State\Chains;

use Noem\State\Callback;
use Noem\State\Chains\Params;
use Noem\State\Middleware\Chain;

/**
 * @template-extends Chain<Callback,Callback>
 */
class PrepareInvokable extends Chain
{
    public function __construct()
    {
        parent::__construct(function (Params\Callback $context): mixed {
            return $context->handler;
        });
    }
}
