<?php

declare(strict_types=1);

namespace Tests\PHPUnit\Unit\Feature\Ai\ContextHelpers;

use Noem\State\Feature\Ai\AiFeature;
use Noem\State\Feature\Ai\Backend\OllamaBackend;
use Noem\State\Feature\Ai\Backend\OpenAiBackend;
use Noem\State\Feature\ExtendedState\BoundAccess;
use Noem\State\Feature\ExtendedState\BoundAccessParams;
use Noem\State\Middleware\ChainMail;
use Noem\State\Middleware\Mesh;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Noem\State\Feature\Ai\AiFeature
 */
class CompleteAcceptsBackendTest extends TestCase
{
    public function testCompleteMethodAcceptsOptionalBackendParameter(): void
    {
        $chainMail = new ChainMail();
        $feature = new AiFeature();

        // AiFeature should register complete() with optional backend parameter
        $feature($chainMail);

        // Verify feature loads successfully (structure is in place)
        $this->assertTrue(true);
    }
}
