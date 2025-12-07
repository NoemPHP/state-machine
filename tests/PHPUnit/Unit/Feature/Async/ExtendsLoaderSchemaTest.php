<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async;

use Nette\Schema\Expect;
use Noem\State\Chains\InvokeCallback;
use Noem\State\Chains\PrepareInvokable;
use Noem\State\Chains\ValidateCallback;
use Noem\State\Events;
use Noem\State\Feature\Async\AsyncFeature;
use Noem\State\Feature\Loader\LoaderChains\Params\SchemaContext;
use Noem\State\Feature\Loader\LoaderChains\Schema;
use Noem\State\Middleware\ChainMail;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: AsyncFeature extends loader schema to accept resolver definitions
 */
#[Group('async'), Group('feature-registration')]
class ExtendsLoaderSchemaTest extends TestCase
{
    public function testExtendsLoaderSchema(): void
    {
        $chainMail = new ChainMail();

        // Provide basic dependencies
        $chainMail->supply(
            fn(): ValidateCallback => new ValidateCallback(),
            fn(): PrepareInvokable => new PrepareInvokable(),
            fn(): InvokeCallback => new InvokeCallback(),
            fn(): \Noem\State\Chains\EnhanceRegionBuilder => new \Noem\State\Chains\EnhanceRegionBuilder(),
            fn(\Noem\State\Chains\ConnectedRegions $connectedRegions): \Noem\State\Chains\Meta => new \Noem\State\Chains\Meta($connectedRegions),
            fn(): \Noem\State\Chains\ConnectedRegions => new \Noem\State\Chains\ConnectedRegions(),
            Events::conjure()
        );

        // Create a Schema chain
        $schema = new Schema();
        $chainMail->supply(fn(): Schema => $schema);

        // Track whether middleware was called
        $middlewareCalled = false;

        // Register AsyncFeature - this should hook into the Schema chain
        $feature = new AsyncFeature();
        $feature($chainMail);

        // Boot ChainMail to execute all queued middleware registrations
        $chainMail->boot();

        // Add a test middleware to verify the chain is being processed
        $schema->link(function (SchemaContext $context, callable $next) use (&$middlewareCalled) {
            $middlewareCalled = true;
            return $next($context);
        });

        // Create a minimal schema context
        $contextSchema = Expect::structure([]);

        $schemaContext = new SchemaContext(
            Expect::anyOf(Expect::string(), Expect::array()),
            Expect::structure([]),
            Expect::structure([]),
            Expect::structure([
                'context' => $contextSchema,
            ]),
        );
        $schemaContext->addCustomSchema('context', $contextSchema);

        // Call the schema chain
        $schema->call($schemaContext);

        // Verify our test middleware was called
        $this->assertTrue($middlewareCalled, 'Schema chain middleware should be executed');

        // Now test that the schema accepts resolvers
        // The AsyncFeature middleware should have extended the context schema
        $processor = new \Nette\Schema\Processor();

        // Test with the updated custom schema
        $updatedContextSchema = $schemaContext->getCustomSchema('context');
        $this->assertNotNull($updatedContextSchema, 'Custom context schema should exist');

        // This should now accept resolvers without throwing an exception
        $processedData = $processor->process($updatedContextSchema, [
            'resolvers' => [
                [
                    'name' => 'testResolver',
                    'run' => 'some_callback_string', // Schema expects string|array, not actual callable
                ],
            ],
        ]);

        // Verify the processed data structure
        $this->assertIsObject($processedData);
        $this->assertObjectHasProperty('resolvers', $processedData);
        $this->assertIsArray($processedData->resolvers);
        $this->assertCount(1, $processedData->resolvers);
        $this->assertEquals('testResolver', $processedData->resolvers[0]->name);
        $this->assertEquals('some_callback_string', $processedData->resolvers[0]->run);
    }
}
