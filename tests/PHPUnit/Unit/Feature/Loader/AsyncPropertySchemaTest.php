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
 * Acceptance Criterion: Async property contains all AsyncConfig fields
 */
#[Group('async'), Group('yaml-callback-extensions')]
class AsyncPropertySchemaTest extends TestCase
{
    public function testAsyncPropertyContainsAllAsyncConfigFields(): void
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

        // Test all AsyncConfig fields are accepted
        $processor = new Processor();
        $processedData = $processor->process($schemaContext->action, [
            'run' => 'some_callback',
            'async' => [
                'enabled' => true,
                'priority' => 'high',
                'singleton' => true,
                'timeout' => 5.0,
                'debounce' => 1.5,
                'throttle' => 0.5,
            ],
        ]);

        $this->assertIsObject($processedData);
        $this->assertObjectHasProperty('async', $processedData);
        $this->assertIsObject($processedData->async);

        // Verify all fields are present
        $this->assertObjectHasProperty('enabled', $processedData->async);
        $this->assertObjectHasProperty('priority', $processedData->async);
        $this->assertObjectHasProperty('singleton', $processedData->async);
        $this->assertObjectHasProperty('timeout', $processedData->async);
        $this->assertObjectHasProperty('debounce', $processedData->async);
        $this->assertObjectHasProperty('throttle', $processedData->async);

        // Verify values
        $this->assertTrue($processedData->async->enabled);
        $this->assertEquals('high', $processedData->async->priority);
        $this->assertTrue($processedData->async->singleton);
        $this->assertEquals(5.0, $processedData->async->timeout);
        $this->assertEquals(1.5, $processedData->async->debounce);
        $this->assertEquals(0.5, $processedData->async->throttle);
    }
}
