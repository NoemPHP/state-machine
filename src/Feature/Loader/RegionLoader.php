<?php

namespace Noem\State\Feature\Loader;

use Noem\State\Chains\BuildRegion;
use Noem\State\Chains\EnhanceRegionBuilder;
use Noem\State\Chains\Params;
use Noem\State\Feature\Feature;
use Noem\State\Feature\Loader\LoaderChains\Context\LoaderContext;
use Noem\State\Feature\Loader\LoaderChains\Loader;
use Noem\State\Feature\Loader\LoaderChains\Schema;
use Noem\State\Feature\Loader\LoaderChains\TransformArray;
use Noem\State\Middleware\Chain;
use Noem\State\Middleware\ChainMail;
use Noem\State\Region;
use Noem\State\RegionBuilder;

/**
 * The Noem State Machine's RegionLoader class is responsible for loading and parsing
 * state machine configurations from YAML or PHP arrays.
 * It contains methods to resolve helper functions, extract configuration data
 * from state definitions, and create callbacks for transition guards and
 * event handlers like entering/exiting states or handling actions.
 * It can load configurations from YAML input using the fromYaml() method
 * or from PHP arrays using the fromArray() method.
 */
class RegionLoader implements Feature
{
    private Chain $loader;

    private ?array $source = null;

    public function __construct()
    {
        $this->loader = new Chain();
    }

    public function withSourceArray(array $data): self
    {
        $this->source = $data;
    }

    public function withYamlSupport(string $stringOrPath, array $helpers): self
    {
        $this->loader->link(function (array $m, callable $n) use ($stringOrPath, $helpers) {
            $m[] = new YamlLoaderMiddleware($stringOrPath, $helpers);

            return $n($m);
        });

        return $this;
    }

    public function load():RegionBuilder
    {

    }

    public function __invoke(ChainMail $chainMail): void
    {
        $chainMail
            ->supply(
                fn(): Schema => new Schema(),
                fn(): TransformArray => new TransformArray(),
                function (Schema $s, TransformArray $t): Loader {
                    return $this->loader->withProvider(function (array $m) use ($s, $t) {
                        $loader = new Loader();
                        $loader->link(new ArrayLoaderMiddleware($s, $t));
                        foreach ($m as $middleware) {
                            $loader->link($middleware);
                        }

                        return $loader;
                    })->call(
                        []
                    );
                }
            )
            /**
             * Inject the Loader chain into the regular build pipeline
             */
            ->use(
                function (EnhanceRegionBuilder $builderEnhancer, Loader $loaderChain): void {
                    foreach ($this->loader as $loaderMiddleware) {
                        $loaderChain->link($loaderMiddleware);
                    }

                    $builderEnhancer->link(
                        function (
                            RegionBuilder $builder,
                            callable $next,
                            callable $first
                        ) use (
                            $loaderChain
                        ): RegionBuilder {
                            $context = new LoaderContext();
                            $context->data = $this->source;
                            $builder = ($loaderChain)->withProvider(fn(LoaderContext $data) => $builder)->call(
                                $context
                            );

                            return $next($builder);
                        }
                    );
                },
            );
    }
}
