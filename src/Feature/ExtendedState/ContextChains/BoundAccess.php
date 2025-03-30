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
            throw new \RuntimeException('bound not found');
        });
    }
}
