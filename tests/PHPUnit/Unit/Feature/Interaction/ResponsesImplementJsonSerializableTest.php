<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Interaction;

use Noem\State\Feature\Interaction\ChoiceResponse;
use Noem\State\Feature\Interaction\ConfirmResponse;
use Noem\State\Feature\Interaction\PromptResponse;
use Noem\State\Feature\Interaction\SelectResponse;
use PHPUnit\Framework\TestCase;

/**
 * @spec All InteractionResponse subclasses implement JsonSerializable interface
 * @see specs/features/interaction.yaml - json-serialization-responses
 */
class ResponsesImplementJsonSerializableTest extends TestCase
{
    public function testConfirmResponseImplementsJsonSerializable(): void
    {
        $response = new ConfirmResponse(confirmed: true);
        $this->assertInstanceOf(\JsonSerializable::class, $response);
    }

    public function testSelectResponseImplementsJsonSerializable(): void
    {
        $response = new SelectResponse(selectedKey: 'key1');
        $this->assertInstanceOf(\JsonSerializable::class, $response);
    }

    public function testChoiceResponseImplementsJsonSerializable(): void
    {
        $response = new ChoiceResponse(selectedKeys: ['key1']);
        $this->assertInstanceOf(\JsonSerializable::class, $response);
    }

    public function testPromptResponseImplementsJsonSerializable(): void
    {
        $response = new PromptResponse(input: 'text');
        $this->assertInstanceOf(\JsonSerializable::class, $response);
    }
}
