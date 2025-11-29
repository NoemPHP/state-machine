<?php

declare(strict_types=1);

namespace Noem\State\Feature\Loader\LoaderChains;

use Noem\State\Feature\Loader\LoaderChains\Params\SchemaContext;
use Noem\State\Middleware\Chain;

/**
 * @template-extends Chain<SchemaContext,null>
 */
class Schema extends Chain
{
    protected int $maxRestarts = -1;
}
