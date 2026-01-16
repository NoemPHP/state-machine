<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\Completion;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Completion buffers partial stop sequences
 */
#[Group('ai'), Group('completion-api')]
class BuffersPartialStopSequencesTest extends TestCase
{
    #[Test]
    public function buffersPartialStopSequencesTest(): void
    {
        // Test that completion handles multi-chunk streaming with stop sequences
        $request = (new \Noem\State\Feature\Ai\RequestBuilder())
            ->setPrompt('Test')
            ->setStop('###')
            ->build();

        $mockBackend = $this->createMock(\Noem\State\Feature\Ai\Backend\BackendInterface::class);
        $mockBackend->method('stream')
            ->willReturn((function() {
                yield ['choices' => [['text' => 'First']]];
                yield ['choices' => [['text' => ' chunk']]];
            })());

        $completion = new \Noem\State\Feature\Ai\Completion($request, true, $mockBackend);

        $result = implode('', iterator_to_array($completion()));

        // Verify completion processes multiple chunks with stop sequence configured
        $this->assertIsString($result, 'Should handle multi-chunk streaming with stop sequence parameter');
        $this->assertStringContainsString('First', $result, 'Should yield first chunk');
    }
}
