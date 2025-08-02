<?php

declare(strict_types=1);

namespace Noem\State\Feature\Template;

use Noem\State\Feature\Async\IO\Fetch;
use Noem\State\Feature\Async\IO\Load;
use Noem\State\Feature\Template\Compiler\Invocation;
use Noem\State\Feature\Template\Compiler\TemplateFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TemplateRendererTest extends TestCase
{

    public function testVariable()
    {
        $factory = new TemplateFactory(new Helpers());
        $template = $factory->create('Howdy {{partner}}');
        $generator = $template([
            'partner' => 'John',
        ]);

        $buffer = '';
        foreach ($generator as $chunk) {
            $buffer .= $chunk;
        }
        $this->assertSame('Howdy John', $buffer);
    }

    public function testVariableHelper()
    {
        $helpers = new Helpers();
        $helpers->registerHelper('foo', function (Invocation $invocation, callable $next) {
            yield 'foo';
            yield from $next($invocation);
        });
        $factory = new TemplateFactory($helpers);
        $template = $factory->create('Howdy {{foo}}');
        $generator = $template();

        $buffer = '';
        foreach ($generator as $chunk) {
            $buffer .= $chunk;
        }
        $this->assertSame('Howdy foo', $buffer);
    }

    public function testVariableHelperWithArgs()
    {
        $helpers = new Helpers();
        $helpers->registerHelper('foo', function (Invocation $invocation, callable $next) {
            $result = $invocation->args[0];
            if ($invocation->hash['uppercase'] === true) {
                $result = strtoupper($result);
            }
            yield $result;
            yield from $next($invocation);
        });
        $factory = new TemplateFactory($helpers);
        $template = $factory->create('Howdy {{foo bar uppercase=true}} {{foo baz uppercase=false}}');
        $generator = $template();

        $buffer = '';
        foreach ($generator as $chunk) {
            $buffer .= $chunk;
        }
        $this->assertSame('Howdy BAR baz', $buffer);
    }

    public function testVariableEscape()
    {
        $factory = new TemplateFactory(new Helpers());
        $template = $factory->create('Howdy {{{partner}}}');
        $generator = $template([
            'partner' => 'John"',
        ]);

        $buffer = '';
        foreach ($generator as $chunk) {
            $buffer .= $chunk;
        }
        $this->assertSame('Howdy John&quot;', $buffer);
    }

    public function testEach()
    {
        $factory = new TemplateFactory(new Helpers());
        $template = $factory->create(
            <<<TPL
/One{{#each numbers}}/{{this}}{{/each}}
TPL
        );
        $generator = $template([
            'numbers' => ['Two', 'Three'],
        ]);

        $buffer = '';
        foreach ($generator as $chunk) {
            $buffer .= $chunk;
        }
        $this->assertSame(
            '/One/Two/Three',
            $buffer
        );
    }

    #[Test] public function eachWithInnerHelper()
    {
        $helpers = new Helpers();
        $helpers->registerHelper('custom', function (Invocation $invocation, callable $next) {
            yield 'a';
            yield 'b';
            yield 'c';
            yield 'd';
            yield 'e';
            yield 'f';
            yield from $next($invocation);
        });
        $factory = new TemplateFactory($helpers);
        $template = $factory->create('{{#each numbers}}{{custom}}{{/each}}');
        $generator = $template([
            'numbers' => [0, 1],
        ]);

        $buffer = '';
        foreach ($generator as $chunk) {
            $buffer .= $chunk;
        }
        $this->assertSame(
            'abcdefabcdef',
            $buffer
        );
    }

    #[Test] public function eachWithInnerBlock()
    {
        $helpers = new Helpers();
        $helpers->registerHelper('custom', function (Invocation $invocation, callable $next) {
            yield 'a';
            yield 'b';
            yield 'c';
            yield from $invocation->blockContent();
            yield from $next($invocation);
        });
        $factory = new TemplateFactory($helpers);
        $template = $factory->create('{{#each numbers}}{{#custom}}def{{/custom}}{{/each}}');
        $generator = $template([
            'numbers' => [0, 1],
        ]);

        $buffer = '';
        foreach ($generator as $chunk) {
            $buffer .= $chunk;
        }
        $this->assertSame(
            'abcdefabcdef',
            $buffer
        );
    }

    #[Test] public function eachWithAsyncInnerBlock()
    {
        $filename = TEST_RESOURCES_DIR.'/content.txt';
        $helpers = new Helpers();
        $helpers->registerHelper('custom', function (Invocation $invocation, callable $next) use ($filename) {
            $load = new Load($filename);
            yield from $load();
            yield from $next($invocation);
        });
        $factory = new TemplateFactory($helpers);
        $template = $factory->create('{{#each numbers}}{{#custom}}{{/custom}}{{/each}}');
        $generator = $template([
            'numbers' => [0, 1],
        ]);

        $buffer = '';
        foreach ($generator as $chunk) {
            $buffer .= $chunk;
        }
        $this->assertSame(
            file_get_contents($filename).file_get_contents($filename),
            $buffer
        );
    }

    #[Test] public function eachWithFetchingInnerBlock()
    {
        $url = 'https://jsonplaceholder.typicode.com/posts/1';
        $title = 'sunt aut facere repellat provident occaecati excepturi optio reprehenderit';
        $helpers = new Helpers();
        $helpers->registerHelper('custom', function (Invocation $invocation, callable $next) use ($url) {
            $load = new Fetch($url)();
            $json = '';
            while ($load->valid()) {
                $json .= $load->current();
                $load->next();
            }
            $payload = json_decode($json);
            yield $payload->title;
            yield from $next($invocation);
        });
        $factory = new TemplateFactory($helpers);
        $template = $factory->create('{{#each numbers}}{{#custom}}{{/custom}}{{/each}}');
        $generator = $template([
            'numbers' => [0, 1],
        ]);

        $buffer = '';
        foreach ($generator as $chunk) {
            $buffer .= $chunk;
        }
        $this->assertSame(
            $title.$title,
            $buffer
        );
    }
}
