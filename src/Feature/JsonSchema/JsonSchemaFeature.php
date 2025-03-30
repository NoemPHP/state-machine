<?php

declare(strict_types=1);

namespace Noem\State\Feature\JsonSchema;

use Nette\Schema\Elements\Structure;
use Nette\Schema\Expect;
use Noem\State\Feature\Feature;
use Noem\State\Feature\Loader\LoaderChains\Context\SchemaContext;
use Noem\State\Middleware\ChainMail;
use Noem\State\Feature\Loader\LoaderChains;

class JsonSchemaFeature implements Feature
{
    public function __invoke(ChainMail $chainMail): void
    {
        $chainMail->supply(
        )->use(
            function (
                ?LoaderChains\Schema $schema,
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
            }
        );
    }
}
