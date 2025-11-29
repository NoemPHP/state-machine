<?php

declare(strict_types=1);

namespace Noem\State\Feature\Async;

use Noem\State\BuildStep;
use Noem\State\Region;
use Noem\State\RegionBuilder;

/**
 * Registers a lazy resolver for a specific context property.
 * 
 * Resolvers enable on-demand computation of context values through async callbacks.
 * When the property is accessed via the mesh, the resolver executes and caches the result.
 */
class AddResolver implements BuildStep
{
    /**
     * @param string $name The property name in the context that this resolver provides
     * @param \Closure $resolver The callback that computes the value (can be sync or async generator)
     */
    public function __construct(
        private readonly string $name,
        private readonly \Closure $resolver
    ) {
    }

    public function callback(RegionBuilder $builder, callable $next, callable $first): Region
    {
        $resolvers = $builder->chainMail->get(Resolvers::class);
        $region = $next($builder);
        $resolvers->addResolver(new ResolverRecord($region, $this->name, $this->resolver));
        return $region;
    }
}
