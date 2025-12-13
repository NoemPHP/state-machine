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
 * Acceptance Criterion: Callbacks without async property work unchanged
 */
#[Group('async'), Group('yaml-callback-extensions')]
class BackwardCompatibilityTest extends TestCase
{
    public function testCallbacksWithoutAsyncPropertyWorkUnchanged(): void
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
            Expect::structure([
                'name' => Expect::string()->required(),
            ]),
            Expect::structure([])
        );

        $schema->call($schemaContext);

        // Test legacy callback format (just run field)
        $processor = new Processor();
        $processedAction = $processor->process($schemaContext->action, [
            'run' => 'legacy_callback',
        ]);

        $this->assertIsObject($processedAction);
        $this->assertObjectHasProperty('run', $processedAction);
        $this->assertEquals('legacy_callback', $processedAction->run);

        // Test state with legacy actions
        $processedState = $processor->process($schemaContext->state, [
            'name' => 'testState',
            'onEnter' => [
                ['run' => 'onEnter_callback'],
            ],
            'onExit' => [
                ['run' => 'onExit_callback'],
            ],
            'action' => [
                ['run' => 'action_callback'],
            ],
        ]);

        $this->assertIsObject($processedState);
        $this->assertCount(1, $processedState->onEnter);
        $this->assertEquals('onEnter_callback', $processedState->onEnter[0]->run);
        $this->assertCount(1, $processedState->onExit);
        $this->assertEquals('onExit_callback', $processedState->onExit[0]->run);
        $this->assertCount(1, $processedState->action);
        $this->assertEquals('action_callback', $processedState->action[0]->run);
    }
}
