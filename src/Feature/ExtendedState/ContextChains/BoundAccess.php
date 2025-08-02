<?php

declare(strict_types=1);

namespace Noem\State\Feature\ExtendedState\ContextChains;

use Noem\State\Feature\ExtendedState\ContextChains\Params\BoundAccessParams;
use Noem\State\Middleware\Chain;

/**
 * @template-extends Chain<BoundAccessParams,mixed>
 */
class BoundAccess extends Chain
{
    public function __construct()
    {
        parent::__construct(function (BoundAccessParams $params) {
            $type = $params->type === BoundAccessParams::TYPE_METHOD
                ? 'Method'
                : 'Property';
            throw new \RuntimeException("$type '{$params->name}' not found in callback context");
        });
    }
}
