<?php

namespace Noem\State\Feature\Transitions;

use Noem\State\BuildStep;
use Noem\State\Region;
use Noem\State\RegionBuilder;

/**
 * Pushes a transition from one state to another based on provided guard condition.
 */
class AddTransition implements BuildStep
{
    /**
     *
     * @param string $from State that triggers this transition
     * @param string $to Target state after successful transition
     * @param ?\Closure $guard Guard callback returning true or false. Optional, allow by default
     *
     */
    public function __construct(
        private readonly string $from,
        private readonly string $to,
        private readonly ?\Closure $guard = null
    ) {
    }

    public function callback(RegionBuilder $builder, callable $next, callable $first): Region
    {
        $transitionRegistry = $builder->chainMail->get(TransitionRegistry::class);
        $region = $next($builder);
        $transitionRegistry->pushTransition($region, $this->from, $this->to, $this->guard);
        return $region;
    }
}
