<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Interaction;

use Noem\State\Feature\Interaction\ConfirmRequest;
use PHPUnit\Framework\TestCase;

/**
 * @spec InteractionRequest.createResponse() validates response class extends InteractionResponse
 * @see specs/features/interaction.yaml - response-creation
 */
class CreateResponseTypeValidationTest extends TestCase
{
    public function testThrowsOnInvalidResponseClass(): void
    {
        $request = new ConfirmRequest(question: 'Test?');

        $this->expectException(\InvalidArgumentException::class);

        $request->createResponse(\stdClass::class, []);
    }
}
