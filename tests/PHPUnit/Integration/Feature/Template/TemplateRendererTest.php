<?php

declare(strict_types=1);

namespace Noem\State\Feature\Template;

use Noem\State\Feature\Template\Compiler\Invocation;
use Noem\State\Feature\Template\Compiler\TemplateFactory;
use Noem\State\Feature\Template\Compiler\TemplateRenderer;
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
Howdy {{#each partners}}
 * {{this}}
{{/each}}   
TPL
        );
        $generator = $template([
            'partners' => ['John', 'Jack'],
        ]);

        $buffer = '';
        foreach ($generator as $chunk) {
            $buffer .= $chunk;
        }
        $this->assertSame(
            <<<TPL
Howdy    
 * John

 * Jack

TPL,
            $buffer
        );
    }
}
