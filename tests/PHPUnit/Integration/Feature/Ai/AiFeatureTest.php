<?php

declare(strict_types=1);

namespace Noem\State\Test\Integration\Feature\Ai;

use Noem\State\Chains\ConnectedRegions;
use Noem\State\Chains\Get;
use Noem\State\Chains\InvokeCallback;
use Noem\State\Chains\Meta;
use Noem\State\Chains\Params\Callback;
use Noem\State\Chains\PrepareInvokable;
use Noem\State\Chains\Set;
use Noem\State\Feature\Ai\AiFeature;
use Noem\State\Feature\Ai\Completion;
use Noem\State\Feature\Async\AsyncFeature;
use Noem\State\Feature\Async\Call;
use Noem\State\Feature\ExtendedState\ContextChains\BoundAccess;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\Template\Compiler\TemplateFactory;
use Noem\State\Feature\Template\Helpers;
use Noem\State\Feature\Template\TemplateFeature;
use Noem\State\Middleware\ChainMail;
use Noem\State\Region;
use Noem\State\Test\Integration\RegionBuilderTestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AiFeatureTest extends RegionBuilderTestCase
{

    protected ChainMail $chainmail;

    protected InvokeCallback $invokeCallback;

    public function setUp(): void
    {
        parent::setUp();
        $this->builder->enableFeatures(
            new ExtendedState(),
            new AsyncFeature(),
            new TemplateFeature(),
            new AiFeature(),
        );
    }

    #[Test]
    public function completion()
    {
        $region = $this
            ->builder
            ->setStates('foo', 'bar')
            ->build();

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
        $region = $this
            ->builder
            ->setStates('foo', 'bar')
            ->build();

        $factory = new TemplateFactory($this->chainmail()->get(Helpers::class));
        $template = $factory->create('A haiku about cats and birds: {{complete}}');
        $generator = $template();

        $buffer = '';
        foreach ($generator as $chunk) {
            $buffer .= $chunk;
        }
        $this->assertSame('Howdy foo', $buffer);
    }

    #[Test]
    public function templateMulti()
    {
        $region = $this
            ->builder
            ->setStates('foo', 'bar')
            ->build();

        $factory = new TemplateFactory($this->chainmail()->get(Helpers::class));
        $template = $factory->create(
            '{{#complete max=2}}Say "foo"{{/complete}}{{#complete max=2}}Say "bar"{{/complete}}{{#complete max=2}}Say "baz"{{/complete}}'
        );
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
        $region = $this
            ->builder
            ->setStates('foo', 'bar')
            ->build();

        $factory = new TemplateFactory($this->chainmail()->get(Helpers::class));
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

    #[Test] public function captureHelper()
    {
        $region = $this
            ->builder
            ->setStates('foo', 'bar')
            ->build();

        $factory = new TemplateFactory($this->chainmail()->get(Helpers::class));
        $template = $factory->create(
            <<<'PROMPT'
Create a list containing only the words "cat", "dog", "bird"!{{capture list}}
{{#each list}}{{this}}{{/each}}
PROMPT

        );
        $generator = $template();

        $buffer = '';
        foreach ($generator as $chunk) {
            $buffer .= $chunk;
        }
        $this->assertSame("Create a list containing only the words \"cat\", \"dog\", \"bird\"!\ncatdogbird", $buffer);
    }

    #[Test] public function captureBlockHelper()
    {
        $region = $this
            ->builder
            ->setStates('foo', 'bar')
            ->build();

        $factory = new TemplateFactory($this->chainmail()->get(Helpers::class));
        $template = $factory->create(
            <<<'PROMPT'
{{#capture list}}Create a list containing only the words "cat", "dog", "bird"!{{/capture}}
{{#each list}}{{this}}{{/each}}
PROMPT

        );
        $generator = $template();

        $buffer = '';
        foreach ($generator as $chunk) {
            $buffer .= $chunk;
        }
        $this->assertSame("Create a list containing only the words \"cat\", \"dog\", \"bird\"!\ncatdogbird", $buffer);
    }
}
