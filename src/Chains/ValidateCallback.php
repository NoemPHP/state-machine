<?php

namespace Noem\State\Chains;

use Noem\State\Chains\Params\Callback;
use Noem\State\Middleware\Chain;
use Noem\State\Util\ParameterDeriver;

/**
 * @template-extends Chain<Callback,bool>
 */
class ValidateCallback extends Chain
{
    public function __construct()
    {
        parent::__construct(function (Callback $context): bool {
            return ParameterDeriver::isCompatibleParameter(
                $context->handler,
                $context->trigger
            );
        });
    }
}
