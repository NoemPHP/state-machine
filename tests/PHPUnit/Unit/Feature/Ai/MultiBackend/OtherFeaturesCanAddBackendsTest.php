<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\MultiBackend;

use Noem\State\Feature\Ai\AiFeature;
use Noem\State\Feature\Ai\Backend\OpenAiBackend;
use Noem\State\Middleware\ChainMail;
use Noem\State\Middleware\Mesh;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Other features can add backends via array assignment ($backends['custom'] = new CustomBackend())
 *
 * Intent: Provides simple backend addition mechanism, enabling features to register
 * custom backends without complex APIs
 */
#[Group('ai'), Group('backend-mesh-infrastructure')]
class OtherFeaturesCanAddBackendsTest extends TestCase
{
    #[Test]
    public function otherFeaturesCanAddBackendsViaArrayAssignment(): void
    {
        $chainMail = new ChainMail();

        $feature = new AiFeature();
        $feature($chainMail);

        $backends = $chainMail->get(Mesh::class);

        // Simulate another feature adding a custom backend
        $customBackend = new OpenAiBackend();
        $backends['custom'] = $customBackend;

        // Verify the custom backend was added
        $this->assertSame($customBackend, $backends['custom']);

        // Verify original backends still exist
        $this->assertTrue(isset($backends['openai']));
        $this->assertTrue(isset($backends['ollama']));
        $this->assertTrue(isset($backends['anthropic']));
    }
}
