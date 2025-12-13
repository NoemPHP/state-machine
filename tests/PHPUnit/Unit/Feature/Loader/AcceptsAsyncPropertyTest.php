<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Loader;

use Nette\Schema\Expect;
use Nette\Schema\Processor;
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
 * Acceptance Criterion: Callback schema accepts optional async property
 */
#[Group('async'), Group('yaml-callback-extensions')]
class AcceptsAsyncPropertyTest extends TestCase
{
    public function testCallbackSchemaAcceptsOptionalAsyncProperty(): void
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

        // Register AsyncFeature
        $feature = new AsyncFeature();
        $feature($chainMail);
        $chainMail->boot();

        // Create schema context
        $contextSchema = Expect::structure([]);
        $schemaContext = new SchemaContext(
            Expect::anyOf(Expect::string(), Expect::array()),
            Expect::structure([
                'run' => Expect::anyOf(Expect::string(), Expect::array()),
            ]),
            Expect::structure([]),
            Expect::structure([])
        );

        // Process schema to get extended action
        $schema->call($schemaContext);

        // Test that action accepts async property
        $processor = new Processor();
        $processedData = $processor->process($schemaContext->action, [
            'run' => 'some_callback',
            'async' => [
                'priority' => 'high',
                'singleton' => true,
            ],
        ]);

        $this->assertIsObject($processedData);
        $this->assertObjectHasProperty('run', $processedData);
        $this->assertObjectHasProperty('async', $processedData);
        $this->assertEquals('some_callback', $processedData->run);
        $this->assertIsObject($processedData->async);
        $this->assertEquals('high', $processedData->async->priority);
        $this->assertTrue($processedData->async->singleton);
    }

    public function testCallbackWithoutAsyncPropertyStillWorks(): void
    {
        $chainMail = new ChainMail();

        $chainMail->supply(
            fn(): ValidateCallback => new ValidateCallback(),
            fn(): PrepareInvokable => new PrepareInvokable(),
            fn(): InvokeCallback => new InvokeCallback(),
            fn(): \Noem\State\Chains\EnhanceRegionBuilder => new \Noem\State\Chains\EnhanceRegionBuilder(),
            fn(\Noem\State\Chains\ConnectedRegions $connectedRegions): \Noem\State\Chains\Meta => new \Noem\State\Chains\Meta($connectedRegions),
            fn(): \Noem\State\Chains\ConnectedRegions => new \Noem\State\Chains\ConnectedRegions(),
            Events::conjure()
        );

        $schema = new Schema();
        $chainMail->supply(fn(): Schema => $schema);

        $feature = new AsyncFeature();
        $feature($chainMail);
        $chainMail->boot();

        $schemaContext = new SchemaContext(
            Expect::anyOf(Expect::string(), Expect::array()),
            Expect::structure([
                'run' => Expect::anyOf(Expect::string(), Expect::array()),
            ]),
            Expect::structure([]),
            Expect::structure([])
        );

        $schema->call($schemaContext);

        // Test that action without async property works
        $processor = new Processor();
        $processedData = $processor->process($schemaContext->action, [
            'run' => 'some_callback',
        ]);

        $this->assertIsObject($processedData);
        $this->assertObjectHasProperty('run', $processedData);
        $this->assertEquals('some_callback', $processedData->run);
    }
}
