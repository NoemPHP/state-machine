<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Interaction\Message;

use Noem\State\Feature\Interaction\InteractionRequest;
use Noem\State\Feature\Interaction\InteractionResponse;
use Noem\State\Feature\Interaction\PromptRequest;
use Noem\State\Feature\Interaction\PromptResponse;
use PHPUnit\Framework\TestCase;

/**
 * Consolidated tests for Prompt pattern message types
 */
class PromptPatternTest extends TestCase
{
    public function testPromptRequestExtendsInteractionRequest(): void
    {
        $request = new PromptRequest(question: 'Enter your name');

        $this->assertInstanceOf(InteractionRequest::class, $request);
    }

    public function testPromptRequestConstructorAcceptsParameters(): void
    {
        $request = new PromptRequest(
            question: 'Enter your name',
            placeholder: 'John Doe',
            defaultValue: 'Guest',
            validation: '^[A-Za-z ]+$',
            multiline: false,
            context: 'User registration',
            timeoutMs: 120000,
            correlationId: 'test-789'
        );

        $this->assertSame('Enter your name', $request->question);
        $this->assertSame('John Doe', $request->placeholder);
        $this->assertSame('Guest', $request->defaultValue);
        $this->assertSame('^[A-Za-z ]+$', $request->validation);
        $this->assertFalse($request->multiline);
        $this->assertSame('User registration', $request->context);
        $this->assertSame(120000, $request->timeoutMs);
        $this->assertSame('test-789', $request->correlationId());
    }

    public function testPromptRequestOptionalParametersAreNull(): void
    {
        $request = new PromptRequest(question: 'Enter your name');

        $this->assertSame('Enter your name', $request->question);
        $this->assertNull($request->placeholder);
        $this->assertNull($request->defaultValue);
        $this->assertNull($request->validation);
        $this->assertFalse($request->multiline);
        $this->assertNull($request->context);
        $this->assertNull($request->timeoutMs);
    }

    public function testPromptRequestGetTypeReturnsPrompt(): void
    {
        $request = new PromptRequest(question: 'Enter your name');

        $this->assertSame('prompt', $request->getType());
    }

    public function testPromptResponseExtendsInteractionResponse(): void
    {
        $response = new PromptResponse(input: 'John Doe');

        $this->assertInstanceOf(InteractionResponse::class, $response);
    }

    public function testPromptResponseConstructorAcceptsParameters(): void
    {
        $response = new PromptResponse(
            input: 'John Doe',
            cancelled: false,
            correlationId: 'test-789'
        );

        $this->assertSame('John Doe', $response->input);
        $this->assertFalse($response->cancelled);
        $this->assertSame('test-789', $response->correlationId());
    }

    public function testPromptResponseInputIsNullWhenCancelled(): void
    {
        $response = new PromptResponse(
            input: null,
            cancelled: true
        );

        $this->assertNull($response->input);
        $this->assertTrue($response->cancelled);
    }
}
