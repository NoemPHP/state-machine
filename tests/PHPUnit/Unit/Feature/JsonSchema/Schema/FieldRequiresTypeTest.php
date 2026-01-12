<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\JsonSchema\Schema;

use Nette\Schema\Expect;
use Nette\Schema\Processor;
use Noem\State\Feature\JsonSchema\JsonSchemaFeature;
use Noem\State\Feature\Loader\LoaderChains;
use Noem\State\Feature\Loader\LoaderChains\Params\SchemaContext;
use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\Feature\Includes\IncludesFeature;
use Noem\State\Middleware\ChainMail;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: Schema field definition requires type as string property
 * Intent: Specifies JSON Schema type (string, number, integer, boolean, array, object) for validation
 * Criticality: contract
 */
final class FieldRequiresTypeTest extends TestCase
{
    public function testSchemaFieldRequiresType(): void
    {
        $chainMail = new ChainMail();
        $chainMail->supply(
            fn(): \Noem\State\Chains\ConnectedRegions => new \Noem\State\Chains\ConnectedRegions(),
            fn(): \Noem\State\Chains\EnhanceRegionBuilder => new \Noem\State\Chains\EnhanceRegionBuilder(),
            fn(): \Noem\State\Chains\ValidateCallback => new \Noem\State\Chains\ValidateCallback(),
            fn(): \Noem\State\Chains\PrepareInvokable => new \Noem\State\Chains\PrepareInvokable(),
            fn(): \Noem\State\Chains\InvokeCallback => new \Noem\State\Chains\InvokeCallback(),
            fn(): \Noem\State\Chains\ExtendedState => new \Noem\State\Chains\ExtendedState(),
            fn(): \Noem\State\Chains\Set => new \Noem\State\Chains\Set(),
            fn(): \Noem\State\Chains\Get => new \Noem\State\Chains\Get(),
            fn(\Noem\State\Chains\ConnectedRegions $c, \Noem\State\Events $e): \Noem\State\Chains\DispatchAction => new \Noem\State\Chains\DispatchAction($c, $e),
            fn(\Noem\State\Chains\ConnectedRegions $connections): \Noem\State\Chains\Path => new \Noem\State\Chains\Path($connections),
            fn(\Noem\State\Chains\ConnectedRegions $connectedRegions): \Noem\State\Chains\Meta => new \Noem\State\Chains\Meta($connectedRegions),
            fn(): \Noem\State\Callbacks\CallbackRegistry => new \Noem\State\Callbacks\CallbackRegistry(),
            \Noem\State\Events::conjure()
        );

        $includesFeature = new IncludesFeature();
        $includesFeature($chainMail);

        $loaderFeature = new RegionLoader();
        $loaderFeature($chainMail);

        $jsonSchemaFeature = new JsonSchemaFeature();
        $jsonSchemaFeature($chainMail);

        $schemaChain = $chainMail->get(LoaderChains\Schema::class);
        $callback = Expect::anyOf(Expect::string(), Expect::array());
        $action = Expect::structure([]);
        $state = Expect::structure(['name' => Expect::string()->required()]);
        $contextSchema = Expect::structure([])->otherItems();
        $region = Expect::structure([
            'name' => Expect::string(),
            'label' => Expect::string(),
            'initial' => Expect::string(),
            'states' => Expect::listOf($state),
            'final' => Expect::string(),
            'context' => $contextSchema,
        ]);
        $context = new SchemaContext($callback, $action, $state, $region);
        $context->addCustomSchema('context', $contextSchema);

        $regionSchema = null;
        $schemaChain->withProvider(function (SchemaContext $ctx) use (&$regionSchema) {
            $regionSchema = $ctx->region;
        })->call($context);

        $processor = new Processor();

        // Test that field with type passes validation
        $normalizedValid = $processor->process($regionSchema, [
            'name' => 'test',
            'context' => [
                'schema' => [
                    ['name' => 'field', 'type' => 'integer'],
                ],
            ],
        ]);

        $schema = (array)$normalizedValid->context->schema;
        $this->assertEquals('integer', $schema[0]['type']);
    }
}
