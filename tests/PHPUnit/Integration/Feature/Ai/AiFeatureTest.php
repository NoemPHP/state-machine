<?php

declare(strict_types=1);

namespace Noem\State\Test\Integration\Feature\Ai;

use Noem\State\Chains\InvokeCallback;
use Noem\State\Chains\Meta;
use Noem\State\Chains\Params\Callback;
use Noem\State\Feature\Ai\AiFeature;
use Noem\State\Feature\Ai\Completion;
use Noem\State\Feature\Async\AsyncFeature;
use Noem\State\Feature\Async\Call;
use Noem\State\Feature\Template\Compiler\TemplateFactory;
use Noem\State\Feature\Template\Helpers;
use Noem\State\Feature\Template\TemplateFeature;
use Noem\State\Middleware\ChainMail;
use Noem\State\Region;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AiFeatureTest extends TestCase
{

    protected ChainMail $chainmail;

    protected InvokeCallback $invokeCallback;

    public function setUp(): void
    {
        $this->invokeCallback = new InvokeCallback();
        $this->chainmail = new ChainMail();
        $meta = \Mockery::mock(Meta::class);
        $meta->allows('link')->andReturn($meta);

        $this->chainmail->supply(
            fn(): InvokeCallback => $this->invokeCallback,
            fn(): Meta => $meta
        );
        $async = new AsyncFeature()($this->chainmail);
        $template = new TemplateFeature()($this->chainmail);
        $ai = new AiFeature()($this->chainmail);
    }

    #[Test]
    public function completion()
    {
        $region = \Mockery::mock(Region::class);
        $coroutine = function () {
            yield from new Completion('Write an essay about cats')();

            return 'main done';
        };

        $callback = new Callback($region, $coroutine, $region);
        $result = '';
        $startTime = microtime(true);

        while ((microtime(true) - $startTime) < 3) {
            $result .= $this->invokeCallback->call($callback);
        }
        $this->assertSame('lol', $result);
    }

    #[Test]
    public function template()
    {
        $region = \Mockery::mock(Region::class);

        $factory = new TemplateFactory($this->chainmail->use(fn(Helpers $h) => $h));
        $template = $factory->create('A haiku about cats and birds: {{complete}}');
        $generator = $template();

        $buffer = '';
        foreach ($generator as $chunk) {
            $buffer .= $chunk;
        }
        $this->assertSame('Howdy foo', $buffer);
    }

    #[Test]
    public function templateBlock()
    {
        $region = \Mockery::mock(Region::class);

        $factory = new TemplateFactory($this->chainmail->use(fn(Helpers $h) => $h));
        $template = $factory->create(
            '{{#complete}}A haiku about cats and birds: {{/complete}}'
        );
        $generator = $template();

        $buffer = '';
        foreach ($generator as $chunk) {
            $buffer .= $chunk;
        }
        $this->assertSame('Howdy foo', $buffer);
    }
}
