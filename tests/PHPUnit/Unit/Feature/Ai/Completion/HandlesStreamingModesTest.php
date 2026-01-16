<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\Completion;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Completion handles streaming and non-streaming responses
 */
#[Group('ai'), Group('completion-api')]
class HandlesStreamingModesTest extends TestCase
{
    #[Test]
    public function handlesStreamingModesTest(): void
    {
        // Test both asText=true and asText=false modes
        $chunks = [
            ['choices' => [['text' => 'Test']]],
            ['choices' => [['text' => ' data']]]
        ];

        // Test asText=true mode
        $mockBackend1 = $this->createMock(\Noem\State\Feature\Ai\Backend\BackendInterface::class);
        $mockBackend1->method('stream')->willReturn((function() use ($chunks) {
            yield from $chunks;
        })());

        $completion1 = new \Noem\State\Feature\Ai\Completion('Test', true, $mockBackend1);
        $textResult = iterator_to_array($completion1());

        $this->assertIsArray($textResult);
        $this->assertIsString($textResult[0], 'asText=true should yield strings');

        // Test asText=false mode
        $mockBackend2 = $this->createMock(\Noem\State\Feature\Ai\Backend\BackendInterface::class);
        $mockBackend2->method('stream')->willReturn((function() use ($chunks) {
            yield from $chunks;
        })());

        $completion2 = new \Noem\State\Feature\Ai\Completion('Test', false, $mockBackend2);
        $jsonResult = iterator_to_array($completion2());

        $this->assertIsArray($jsonResult);
        $this->assertIsArray($jsonResult[0], 'asText=false should yield arrays');
        $this->assertArrayHasKey('choices', $jsonResult[0], 'Should yield raw JSON structure');
    }
}
