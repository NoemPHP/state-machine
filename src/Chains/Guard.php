<?php

declare(strict_types=1);

namespace Noem\State\Chains;

use Noem\State\Chains\Params;
use Noem\State\Middleware\Chain;
use Noem\State\Util\ParameterDeriver;

/**
 * @template-extends Chain<Guard,bool>
 */
class Guard extends Chain
{
    public function __construct(
        InvokeCallback $invokeCallback
    ) {
        parent::__construct(function (Params\Guard $ctx) use ($invokeCallback): bool {
            if (
                !ParameterDeriver::isCompatibleParameter(
                    $ctx->handler,
                    $ctx->trigger
                )
            ) {
                return false;
            }
            if (ParameterDeriver::getReturnType($ctx->handler) !== 'bool') {
                throw new \RuntimeException(
                    "Invalid guard callback for a transition from '{$ctx->origin}' to '{$ctx->target}':\n
                         Guards must return bool"
                );
            }
            $callbackContext = new Params\Callback($ctx->region, $ctx->handler, $ctx->trigger);

            return $invokeCallback->call($callbackContext);
        });
    }
}
