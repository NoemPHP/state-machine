<?php

declare(strict_types=1);

namespace Noem\State\Feature\Transitions\Chains;

use Noem\State\Chains\InvokeCallback;
use Noem\State\Chains\Params\Callback;
use Noem\State\Chains\PrepareInvokable;
use Noem\State\Middleware\Chain;
use Noem\State\Util\ParameterDeriver;

/**
 * @template-extends Chain<Guard,bool>
 */
class Guard extends Chain
{
    public function __construct(
        InvokeCallback $invokeCallback,
        PrepareInvokable $prepareInvokable
    ) {
        parent::__construct(function (Params\Guard $ctx) use ($invokeCallback, $prepareInvokable): bool {
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
            $callbackContext = new Callback($ctx->region, $ctx->handler, $ctx->trigger);
            $invokable = $prepareInvokable->call($callbackContext);
            $context = new Callback($ctx->region, $invokable, $ctx->trigger);

            return $invokeCallback->call($context);
        });
    }
}
