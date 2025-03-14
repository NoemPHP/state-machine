<?php

declare(strict_types=1);

namespace Noem\State\Chains;

use Noem\State\Middleware\Chain;
use Noem\State\Middleware\Mesh;

/**
 * @template-extends Chain<ExtendedState,Mesh>
 */
class ExtendedState extends Chain
{

    /** @noinspection PhpVariableIsUsedOnlyInClosureInspection */
    public function __construct()
    {
        $stateCache = new \SplObjectStorage();
        $regionCache = new \SplObjectStorage();
        parent::__construct(function (Params\ExtendedState $ctx) use ($regionCache, $stateCache): Mesh {
            $cache = is_null($ctx->state)
                ? $regionCache
                : $stateCache;
            $hasCache = $cache->contains($ctx->region);
            if (!$hasCache) {
                $data = new Mesh();
                $cache->attach($ctx->region, $data);
            }
            $hasCache && $ctx->pristine = false;

            return $cache[$ctx->region];
        });
    }
}
