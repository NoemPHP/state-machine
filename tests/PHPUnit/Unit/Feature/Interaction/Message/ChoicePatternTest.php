<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Interaction\Message;

use Noem\State\Feature\Interaction\ChoiceOption;
use Noem\State\Feature\Interaction\ChoiceRequest;
use Noem\State\Feature\Interaction\ChoiceResponse;
use Noem\State\Feature\Interaction\InteractionRequest;
use Noem\State\Feature\Interaction\InteractionResponse;
use PHPUnit\Framework\TestCase;

/**
 * Consolidated tests for Choice pattern message types
 */
class ChoicePatternTest extends TestCase
{
    public function testChoiceRequestExtendsInteractionRequest(): void
    {
        $request = new ChoiceRequest(
            question: 'Select features',
            options: ['auth' => new ChoiceOption('Authentication')]
        );

        $this->assertInstanceOf(InteractionRequest::class, $request);
    }

    public function testChoiceRequestConstructorAcceptsParameters(): void
    {
        $options = [
            'auth' => new ChoiceOption('Authentication', 'User login system', recommended: true),
            'cache' => new ChoiceOption('Caching', 'Performance optimization'),
        ];

        $request = new ChoiceRequest(
            question: 'Select features',
            options: $options,
            defaultKeys: ['auth'],
            minSelections: 1,
            maxSelections: 3,
            context: 'Feature selection',
            timeoutMs: 60000,
            correlationId: 'test-456'
        );

        $this->assertSame('Select features', $request->question);
        $this->assertSame($options, $request->options);
        $this->assertSame(['auth'], $request->defaultKeys);
        $this->assertSame(1, $request->minSelections);
        $this->assertSame(3, $request->maxSelections);
        $this->assertSame('Feature selection', $request->context);
        $this->assertSame(60000, $request->timeoutMs);
        $this->assertSame('test-456', $request->correlationId());
    }

    public function testChoiceRequestGetTypeReturnsChoice(): void
    {
        $request = new ChoiceRequest(
            question: 'Select features',
            options: ['auth' => new ChoiceOption('Authentication')]
        );

        $this->assertSame('choice', $request->getType());
    }

    public function testChoiceOptionConstructorAcceptsParameters(): void
    {
        $option = new ChoiceOption('Authentication', 'User login system', recommended: true);

        $this->assertSame('Authentication', $option->label);
        $this->assertSame('User login system', $option->description);
        $this->assertTrue($option->recommended);
    }

    public function testChoiceOptionDescriptionAndRecommendedAreOptional(): void
    {
        $option = new ChoiceOption('Authentication');

        $this->assertSame('Authentication', $option->label);
        $this->assertNull($option->description);
        $this->assertFalse($option->recommended);
    }

    public function testChoiceResponseExtendsInteractionResponse(): void
    {
        $response = new ChoiceResponse(selectedKeys: ['auth', 'cache']);

        $this->assertInstanceOf(InteractionResponse::class, $response);
    }

    public function testChoiceResponseConstructorAcceptsParameters(): void
    {
        $response = new ChoiceResponse(
            selectedKeys: ['auth', 'cache'],
            cancelled: false,
            correlationId: 'test-456'
        );

        $this->assertSame(['auth', 'cache'], $response->selectedKeys);
        $this->assertFalse($response->cancelled);
        $this->assertSame('test-456', $response->correlationId());
    }

    public function testChoiceResponseSelectedKeysIsEmptyWhenCancelled(): void
    {
        $response = new ChoiceResponse(
            selectedKeys: [],
            cancelled: true
        );

        $this->assertEmpty($response->selectedKeys);
        $this->assertTrue($response->cancelled);
    }
}
