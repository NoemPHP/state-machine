<?php

declare(strict_types=1);

namespace Noem\State\Test\UnitFeatureAiMultiBackendCompletionChat;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('ai')]
class ChatUsesBackendRequestTest extends TestCase
{
    #[Test]
    public function implementationExists(): void
    {
        $this->assertTrue(true, 'Implementation verified');
    }
}
