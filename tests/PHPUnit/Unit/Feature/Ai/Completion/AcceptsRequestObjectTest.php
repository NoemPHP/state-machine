<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\Completion;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Completion accepts pre-built Request object
 */
#[Group('ai'), Group('completion-api')]
class AcceptsRequestObjectTest extends TestCase
{
    #[Test]
    public function acceptsRequestObject(): void
    {
        $request = (new \Noem\State\Feature\Ai\RequestBuilder())
            ->setPrompt('Test prompt')
            ->build();

        // Create mock backend
        $mockBackend = $this->createMock(\Noem\State\Feature\Ai\Backend\BackendInterface::class);

        $completion = new \Noem\State\Feature\Ai\Completion(
            $request,
            true,
            $mockBackend
        );

        $this->assertInstanceOf(\Noem\State\Feature\Ai\Completion::class, $completion, 'Should construct with Request object');
    }
}
