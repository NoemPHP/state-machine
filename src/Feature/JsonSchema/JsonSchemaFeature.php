<?php

declare(strict_types=1);

namespace Noem\State\Feature\JsonSchema;

use Nette\Schema\Elements\Structure;
use Nette\Schema\Expect;
use Noem\State\Chains\Meta;
use Noem\State\Feature\ExtendedState\ContextMetaType;
use Noem\State\Feature\Feature;
use Noem\State\Feature\Loader\LoaderChains\Context\SchemaContext;
use Noem\State\Middleware\ChainMail;
use Noem\State\Feature\Loader\LoaderChains;
use Noem\State\RegionBuilder;

class JsonSchemaFeature implements Feature
{

    public function __invoke(ChainMail $chainMail): void
    {
        $chainMail->supply()->use(
            function (
                ?LoaderChains\Schema $schema,
                ?LoaderChains\Loader $loader
            ) {
                /**
                 * Extend the region schema to support the 'regions' item within a state config
                 */
                $schema?->link(function (SchemaContext $context, callable $next) {
                    $contextSchema = $context->getCustomSchema('context');
                    assert($contextSchema instanceof Structure);
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

                    return $next($context);
                });
                /**
                 * Setup schema defaults through the builder chains
                 */
                $loader?->link(function (LoaderChains\Context\LoaderContext $context, callable $next) {
                    $data = $context->data;
                    if (!isset($data['context']['schema'])) {
                        return $next($context);
                    }
                    $schema = $data['context']['schema'];
                    $builder = $next($context);
                    assert($builder instanceof RegionBuilder);
                    $builder->addStep(function (RegionBuilder $builder, callable $next) use ($schema) {
                        $meta = $builder->chainMail->get(Meta::class);
                        $region = $next($builder);
                        $metadata = $meta->call(new \Noem\State\Chains\Params\Meta($region, ContextMetaType::get()));
                        foreach ($schema as $type) {
                            $metadata[$type['name']] = $type['default'] ?? null;
                        }

                        return $region;
                    });

                    return $builder;
                });
            }
        );
    }
}
