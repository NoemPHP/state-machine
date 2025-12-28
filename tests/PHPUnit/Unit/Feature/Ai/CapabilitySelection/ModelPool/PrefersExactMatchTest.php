<?php

declare(strict_types=1);

namespace Tests\PHPUnit\Unit\Feature\Ai\CapabilitySelection\ModelPool;

use Noem\State\Feature\Ai\ModelPool;
use PHPUnit\Framework\TestCase;

class PrefersExactMatchTest extends TestCase
{
    public function testPrefersExactMatchOverHigherCapability(): void
    {
        $pool = new ModelPool();

        $pool->addModel('exact', 'openai', 'gpt-3.5', 2, 8, 'low');
        $pool->addModel('higher', 'openai', 'gpt-4', 4, 128, 'high');

        $result = $pool->selectModel(complexity: 2, context: 8);

        $this->assertEquals('exact', $result['id']);
    }
}
