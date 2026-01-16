<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Interaction;

use Noem\State\Feature\Interaction\ChoiceRequest;
use Noem\State\Feature\Interaction\ConfirmRequest;
use Noem\State\Feature\Interaction\PromptRequest;
use Noem\State\Feature\Interaction\SelectRequest;
use PHPUnit\Framework\TestCase;

/**
 * @spec All InteractionRequest subclasses implement JsonSerializable interface
 * @see specs/features/interaction.yaml - json-serialization-requests
 */
class RequestsImplementJsonSerializableTest extends TestCase
{
    public function testConfirmRequestImplementsJsonSerializable(): void
    {
        $request = new ConfirmRequest(question: 'Test?');
        $this->assertInstanceOf(\JsonSerializable::class, $request);
    }

    public function testSelectRequestImplementsJsonSerializable(): void
    {
        $request = new SelectRequest(question: 'Test?', options: []);
        $this->assertInstanceOf(\JsonSerializable::class, $request);
    }

    public function testChoiceRequestImplementsJsonSerializable(): void
    {
        $request = new ChoiceRequest(question: 'Test?', options: []);
        $this->assertInstanceOf(\JsonSerializable::class, $request);
    }

    public function testPromptRequestImplementsJsonSerializable(): void
    {
        $request = new PromptRequest(question: 'Test?');
        $this->assertInstanceOf(\JsonSerializable::class, $request);
    }
}
