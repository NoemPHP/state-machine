<?php

declare(strict_types=1);

namespace Noem\State\Test\Integration\Feature\Template;

use Noem\State\Chains;
use Noem\State\Chains\Params;
use Noem\State\Events;
use Noem\State\Feature\Async\AsyncFeature;
use Noem\State\Feature\Async\Call;
use Noem\State\Feature\ExtendedState\ContextMetaType;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\Template\Helpers;
use Noem\State\Feature\Template\TemplateFeature;
use Noem\State\MetaType;
use Noem\State\Middleware\ChainMail;
use Noem\State\Region;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TemplateFeatureTest extends TestCase
{

    #[Test]
    public function happyPath()
    {
        $this->markTestSkipped();
        $chainMail = new ChainMail();
        $feature = new TemplateFeature();
        $feature($chainMail);
        $helpers = $chainMail->get(Helpers::class);
    }

    #[Test] public function usageInCallback()
    {
        $chainMail = new ChainMail();
        $chainMail->supply(
            fn(): Chains\ValidateCallback => new Chains\ValidateCallback(),
            fn(): Chains\PrepareInvokable => new Chains\PrepareInvokable(),
            fn(): Chains\InvokeCallback => new Chains\InvokeCallback(),
            fn(): Chains\ConnectedRegions => new Chains\ConnectedRegions(),
            Events::conjure(),
            fn(): Chains\Get => new Chains\Get(),
            fn(): Chains\Set => new Chains\Set(),
            fn(): Chains\ExtendedState => new Chains\ExtendedState(),
            fn(Chains\ConnectedRegions $connectedRegions): Chains\Meta => new Chains\Meta($connectedRegions),

        );
        new TemplateFeature()($chainMail);
        new AsyncFeature()($chainMail);
        new ExtendedState()($chainMail);
        $meta = $chainMail->get(Chains\Meta::class);
        $invoke = $chainMail->get(Chains\InvokeCallback::class);
        $events = $chainMail->get(Events::class);
        assert($events instanceof Events);
        $chainMail->boot();
        $cb = function (object $trigger) {
            $template = $this->template('rofl');
            assert($template instanceof \Generator);
            while ($template->valid()) {
                $template->next();
                yield;
            }
            $result = $template->getReturn();

            $this->set('result', $result);
        };
        $region = \Mockery::mock(Region::class);
        //$callback = new Callback($region, $cb, new \stdClass());
        $events->addActionHandler($region, 'lol', $cb);
        $events->onAction($region, 'lol', new \stdClass());
        $events->onAction($region, 'lol', new \stdClass());
        $events->onAction($region, 'lol', new \stdClass());
        $events->onAction($region, 'lol', new \stdClass());
        $events->onAction($region, 'lol', new \stdClass());
        $metadata = $meta->call(new Params\Meta($region, ContextMetaType::get()));
        $this->assertSame($metadata['result'], 'rofl');
    }

    #[Test] public function usageInTask()
    {
        $chainMail = new ChainMail();
        $chainMail->supply(
            fn(): Chains\ValidateCallback => new Chains\ValidateCallback(),
            fn(): Chains\PrepareInvokable => new Chains\PrepareInvokable(),
            fn(): Chains\InvokeCallback => new Chains\InvokeCallback(),
            fn(): Chains\ConnectedRegions => new Chains\ConnectedRegions(),
            Events::conjure(),
            fn(): Chains\Get => new Chains\Get(),
            fn(): Chains\Set => new Chains\Set(),
            fn(): Chains\ExtendedState => new Chains\ExtendedState(),
            fn(Chains\ConnectedRegions $connectedRegions): Chains\Meta => new Chains\Meta($connectedRegions),

        );
        new TemplateFeature()($chainMail);
        new AsyncFeature()($chainMail);
        new ExtendedState()($chainMail);
        $meta = $chainMail->get(Chains\Meta::class);
        $invoke = $chainMail->get(Chains\InvokeCallback::class);
        $events = $chainMail->get(Events::class);
        assert($events instanceof Events);
        $chainMail->boot();
        $cb = function (object $trigger) {
            $this->set('result', yield Call::call($this->template('rofl')));
        };
        $region = \Mockery::mock(Region::class);
        //$callback = new Callback($region, $cb, new \stdClass());
        $events->addActionHandler($region, 'lol', $cb);
        $events->onAction($region, 'lol', new \stdClass());
        $events->onAction($region, 'lol', new \stdClass());
        $events->onAction($region, 'lol', new \stdClass());
        $events->onAction($region, 'lol', new \stdClass());
        $events->onAction($region, 'lol', new \stdClass());
        $metadata = $meta->call(new Params\Meta($region, ContextMetaType::get()));
        $this->assertSame($metadata['result'], 'rofl');
    }

}
