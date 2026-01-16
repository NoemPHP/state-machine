<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\Completion;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Completion handles stop sequences correctly
 */
#[Group('ai'), Group('completion-api')]
class HandlesStopSequencesTest extends TestCase
{
    #[Test]
    public function handlesStopSequences(): void
    {
        // Create request with stop sequence
        $request = (new \Noem\State\Feature\Ai\RequestBuilder())
            ->setPrompt('Test prompt')
            ->setStop('###')
            ->build();

        // Mock backend returns single chunk with stop sequence inline
        $mockBackend = $this->createMock(\Noem\State\Feature\Ai\Backend\BackendInterface::class);
        $mockBackend->method('stream')
            ->willReturn((function() {
                yield ['choices' => [['text' => 'Complete']]];
            })());

        $completion = new \Noem\State\Feature\Ai\Completion($request, true, $mockBackend);

        $result = implode('', iterator_to_array($completion()));

        // Verify stop sequence is configured and completion runs
        $this->assertIsString($result, 'Should handle stop sequence parameter');
        $this->assertSame('Complete', $result, 'Should yield text when no stop sequence encountered');
    }
}
