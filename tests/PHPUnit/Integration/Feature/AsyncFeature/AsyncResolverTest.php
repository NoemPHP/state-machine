<?php

declare(strict_types=1);

namespace Noem\State\Test\Integration\Feature\AsyncFeature;

use Noem\State\Chains;
use Noem\State\Chains\Params;
use Noem\State\Feature\Async\AsyncFeature;
use Noem\State\Feature\Async\ResolverRecord;
use Noem\State\Feature\Async\Resolvers;
use Noem\State\Feature\ExtendedState\ContextMetaType;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Middleware\ChainMail;
use Noem\State\Region;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AsyncResolverTest extends TestCase
{

    protected ChainMail $chainmail;

    protected Chains\Meta $meta;

    protected Chains\InvokeCallback $invokeCallback;

    protected Resolvers $resolve;

    protected Chains\ConnectedRegions $connectedRegions;

    public function setUp(): void
    {
        $this->invokeCallback = new Chains\InvokeCallback();
        $this->connectedRegions = new Chains\ConnectedRegions();
        $this->meta = new Chains\Meta($this->connectedRegions);
        $this->chainmail = new ChainMail();
        $this->chainmail->supply(
            fn(): Chains\InvokeCallback => $this->invokeCallback,
            fn(): Chains\Meta => $this->meta,
            fn(): Chains\Get => new Chains\Get(),
            fn(): Chains\Set => new Chains\Set(),
            fn(): Chains\ExtendedState => new Chains\ExtendedState(),
            fn(): Chains\ConnectedRegions => new Chains\ConnectedRegions()
        );
        new ExtendedState()($this->chainmail);
        $async = new AsyncFeature()($this->chainmail);
        $this->resolve = $this->chainmail->use(fn(Resolvers $r) => $r);
    }

    #[Test]
    public function simpleResolve()
    {
        $region = \Mockery::mock(Region::class);
        $key = 'foo';
        $record = new ResolverRecord($region, $key, function () {
            yield;
            yield;

            return 'lol';
        });
        $this->resolve->addResolver($record);
        $metaParams = new Params\Meta($region, ContextMetaType::get());
        $mesh = $this->meta->call($metaParams);
        $value = $mesh[$key];
        $value = $mesh[$key];
        $this->assertSame(null, $value);

        $callback = new Params\Callback($region, fn() => true, $region);
        $this->invokeCallback->call($callback);
        $this->invokeCallback->call($callback);
        $this->invokeCallback->call($callback);
        $this->invokeCallback->call($callback);
        $this->invokeCallback->call($callback);
        $this->invokeCallback->call($callback);
        $value = $mesh[$key];
        $this->assertSame('lol', $value);
    }

    #[Test]
    public function withDependency()
    {
        $region = \Mockery::mock(Region::class);
        $key = 'foo';
        $record = new ResolverRecord($region, $key, function () {
            yield;
            $dep = $this->get('bar');
            yield;

            return $dep.' world';
        });
        $this->resolve->addResolver($record);
        $metaParams = new Params\Meta($region, ContextMetaType::get());
        $mesh = $this->meta->call($metaParams);
        $mesh['bar'] = 'hello';
        $value = $mesh[$key];
        $value = $mesh[$key];
        $this->assertSame(null, $value);

        $callback = new Params\Callback($region, fn() => true, $region);
        $this->invokeCallback->call($callback);
        $this->invokeCallback->call($callback);
        $this->invokeCallback->call($callback);
        $this->invokeCallback->call($callback);
        $this->invokeCallback->call($callback);
        $this->invokeCallback->call($callback);
        $this->invokeCallback->call($callback);
        $value = $mesh[$key];
        $this->assertSame('hello world', $value);
    }
}
