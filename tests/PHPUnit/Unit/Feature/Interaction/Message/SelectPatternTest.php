<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Interaction\Message;

use Noem\State\Feature\Interaction\InteractionRequest;
use Noem\State\Feature\Interaction\InteractionResponse;
use Noem\State\Feature\Interaction\SelectOption;
use Noem\State\Feature\Interaction\SelectRequest;
use Noem\State\Feature\Interaction\SelectResponse;
use PHPUnit\Framework\TestCase;

/**
 * Consolidated tests for Select pattern message types
 */
class SelectPatternTest extends TestCase
{
    public function testSelectRequestExtendsInteractionRequest(): void
    {
        $request = new SelectRequest(
            question: 'Choose backend',
            options: ['mysql' => new SelectOption('MySQL')]
        );

        $this->assertInstanceOf(InteractionRequest::class, $request);
    }

    public function testSelectRequestConstructorAcceptsParameters(): void
    {
        $options = [
            'mysql' => new SelectOption('MySQL', 'Traditional database'),
            'postgres' => new SelectOption('PostgreSQL', 'Advanced features'),
        ];

        $request = new SelectRequest(
            question: 'Choose backend',
            options: $options,
            defaultKey: 'mysql',
            context: 'Production deployment',
            timeoutMs: 30000,
            correlationId: 'test-123'
        );

        $this->assertSame('Choose backend', $request->question);
        $this->assertSame($options, $request->options);
        $this->assertSame('mysql', $request->defaultKey);
        $this->assertSame('Production deployment', $request->context);
        $this->assertSame(30000, $request->timeoutMs);
        $this->assertSame('test-123', $request->correlationId());
    }

    public function testSelectRequestGetTypeReturnsSelect(): void
    {
        $request = new SelectRequest(
            question: 'Choose backend',
            options: ['mysql' => new SelectOption('MySQL')]
        );

        $this->assertSame('select', $request->getType());
    }

    public function testSelectOptionConstructorAcceptsLabelAndDescription(): void
    {
        $option = new SelectOption('MySQL', 'Traditional database');

        $this->assertSame('MySQL', $option->label);
        $this->assertSame('Traditional database', $option->description);
    }

    public function testSelectOptionDescriptionIsOptional(): void
    {
        $option = new SelectOption('MySQL');

        $this->assertSame('MySQL', $option->label);
        $this->assertNull($option->description);
    }

    public function testSelectResponseExtendsInteractionResponse(): void
    {
        $response = new SelectResponse(selectedKey: 'mysql');

        $this->assertInstanceOf(InteractionResponse::class, $response);
    }

    public function testSelectResponseConstructorAcceptsParameters(): void
    {
        $response = new SelectResponse(
            selectedKey: 'mysql',
            cancelled: false,
            correlationId: 'test-123'
        );

        $this->assertSame('mysql', $response->selectedKey);
        $this->assertFalse($response->cancelled);
        $this->assertSame('test-123', $response->correlationId());
    }

    public function testSelectResponseSelectedKeyIsNullWhenCancelled(): void
    {
        $response = new SelectResponse(
            selectedKey: null,
            cancelled: true
        );

        $this->assertNull($response->selectedKey);
        $this->assertTrue($response->cancelled);
    }
}
