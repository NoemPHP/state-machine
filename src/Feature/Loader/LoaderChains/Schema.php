<?php

declare(strict_types=1);

namespace Noem\State\Feature\Loader\LoaderChains;

use Noem\State\Feature\Loader\LoaderChains\Context\LoaderContext;
use Noem\State\Feature\Loader\LoaderChains\Context\SchemaContext;
use Noem\State\Middleware\Chain;
use Noem\State\RegionBuilder;

/**
 * @template-extends Chain<SchemaContext,null>
 */
class Schema extends Chain
{
    protected int $maxRestarts = -1;
}
