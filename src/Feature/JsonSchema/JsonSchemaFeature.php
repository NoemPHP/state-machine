<?php

declare(strict_types=1);

namespace Noem\State\Feature\JsonSchema;

use Nette\Schema\Elements\Structure;
use Nette\Schema\Expect;
use Noem\State\Chains\EnhanceRegionBuilder;
use Noem\State\Chains\Meta;
use Noem\State\Chains\Params\BuildParams;
use Noem\State\Feature\ExtendedState\ContextMetaType;
use Noem\State\Feature\Feature;
use Noem\State\Feature\Loader\LoaderChains\Params\SchemaContext;
use Noem\State\Middleware\ChainMail;
use Noem\State\Feature\Loader\LoaderChains;
use Noem\State\RegionBuilder;

class JsonSchemaFeature implements Feature
{
    public function __invoke(ChainMail $chainMail): void
    {
        $chainMail->supply()->use(
            function (
                EnhanceRegionBuilder $enhanceRegionBuilder,
                ?LoaderChains\Schema $schema
            ) {
                /**
                 * Extend the context schema to recognize the 'schema' entry
                 */
                $schema?->link(function (SchemaContext $context, callable $next) {
                    $contextSchema = $context->getCustomSchema('context');
                    // Only extend if context schema exists (RegionLoader may not be loaded)
                    if ($contextSchema instanceof Structure) {
                        $contextSchema = $contextSchema->extend([
                            'schema' => Expect::listOf(
                                Expect::structure(
                                    [
                                        'name' => Expect::string(),
                                        'type' => Expect::string(),
                                        'default' => Expect::string(),
                                        'description' => Expect::string(),
                                    ]
                                )
                            ),
                        ]);
                        $context->addCustomSchema('context', $contextSchema);
                        /**
                         * Update the reference on the region schema since we just produced a new object
                         */
                        $context->region = $context->region->extend([
                            'context' => $contextSchema,
                        ]);
                    }

                    return $next($context);
                });
                /**
                 * Setup schema defaults through the builder chains
                 */
                $enhanceRegionBuilder?->link(function (BuildParams $context, callable $next) {
                    $loaderConfig = $context->config(\Noem\State\Chains\Params\Config\LoaderConfig::class);
                    if (!$loaderConfig->hasContext('schema')) {
                        return $next($context);
                    }

                    $schema = $loaderConfig->context('schema');
                    $builder = $next($context);
                    assert($builder instanceof RegionBuilder);
                    $builder->addBuildStep(new AddJsonSchema($schema));

                    return $builder;
                });
            }
        );
    }
}
