<?php

namespace Noem\State\Feature\Components;

use Nette\Schema\Elements\Structure;
use Nette\Schema\Expect;
use Noem\State\Chains\DispatchAction;
use Noem\State\Chains\Meta;
use Noem\State\Chains\Params\Action;
use Noem\State\Feature\Feature;
use Noem\State\Feature\Loader\LoaderChains\Params\SchemaContext;
use Noem\State\Feature\Loader\LoaderChains\Schema;
use Noem\State\Middleware\ChainMail;

/**
 * This feature defines a way to attach reusable behaviour to any state without writing custom logic.
 * It works similar to the Entity/Component/System pattern - just with states instead of entities
 */
class ComponentsFeature implements Feature
{

    public function __invoke(ChainMail $chainMail): void
    {
        $chainMail
            ->supply()
            ->use($this->setupLoaderSchema(...))
            ->use($this->setupComponentSystems(...));
    }

    /**
     * Extend the region schema to support the 'regions' item within a state config
     */
    private function setupLoaderSchema(?Schema $schema)
    {

        $schema?->link(function (SchemaContext $context, callable $next) {
            $contextSchema = Expect::listOf(
                Expect::structure(
                    [
                        'name' => Expect::string(),
                        'initial' => Expect::array(),
                    ]
                )
            );
            $context->addCustomSchema('components', $contextSchema);
            return $next($context);
        });
    }

    /**
     * When an action is dispatched, execute all component systems belonging to the current state/region.
     *
     * @param DispatchAction $dispatchAction
     * @param Meta $meta
     * @return void
     */
    private function setupComponentSystems(
        DispatchAction $dispatchAction,
        Meta           $meta
    )
    {
        $dispatchAction->link(function (Action $action, callable $next) use ($meta) {
            $components = $meta->call(new \Noem\State\Chains\Params\Meta($action->region, ComponentsMetaType::get()));
        });
    }
}