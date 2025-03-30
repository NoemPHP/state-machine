<?php

declare(strict_types=1);

namespace Noem\State\Feature\Loader\LoaderChains;

use Noem\State\Feature\Loader\ArrayLoaderMiddleware;
use Noem\State\Feature\Loader\LoaderChains\Context\LoaderContext;
use Noem\State\Middleware\Chain;
use Noem\State\RegionBuilder;

/**
 * @template-extends Chain<LoaderContext,RegionBuilder>
 */
class Loader extends Chain
{
    protected int $maxRestarts = -1;

    //public function __construct(Schema $schema)
    //{
    //    parent::__construct(function (array $m) use ($schema) {
    //        $loader = new Loader();
    //        $loader->link(new ArrayLoaderMiddleware($schema));
    //        foreach ($m as $middleware) {
    //            $loader->link($middleware);
    //        }
    //
    //        return $loader;
    //    });
    //}
}
