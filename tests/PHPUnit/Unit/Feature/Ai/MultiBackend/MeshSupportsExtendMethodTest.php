<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\MultiBackend;

use Noem\State\Feature\Ai\AiFeature;
use Noem\State\Middleware\ChainMail;
use Noem\State\Middleware\Mesh;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Backend mesh supports extend() method for chain-based extensions
 *
 * Intent: Enables chain-based backend mesh extension, allowing features to intercept
 * backend access operations
 */
#[Group('ai'), Group('backend-mesh-infrastructure')]
class MeshSupportsExtendMethodTest extends TestCase
{
    #[Test]
    public function meshSupportsExtendMethod(): void
    {
        $chainMail = new ChainMail();

        $feature = new AiFeature();
        $feature($chainMail);

        $backends = $chainMail->get(Mesh::class);

        // Verify extend method exists and can be called
        $called = false;
        $backends->extend(
            offsetGet: function ($offset, callable $next) use (&$called) {
                $called = true;
                return $next($offset);
            }
        );

        // Access a backend to trigger the extension
        $backend = $backends['openai'];

        $this->assertTrue($called, 'Extended offsetGet callback should be called');
        $this->assertNotNull($backend);
    }
}
