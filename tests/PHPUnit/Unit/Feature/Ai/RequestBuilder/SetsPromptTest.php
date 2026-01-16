<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\RequestBuilder;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: RequestBuilder allows setting prompt
 */
#[Group('ai'), Group('request-building')]
class SetsPromptTest extends TestCase
{
    #[Test]
    public function setsPrompt(): void
    {
        $builder = new \Noem\State\Feature\Ai\RequestBuilder();

        $result = $builder->setPrompt('Hello, AI!');

        $this->assertSame($builder, $result, 'setPrompt should return self for fluent interface');
        $this->assertSame('Hello, AI!', $builder['prompt'], 'Prompt should be set correctly');
    }
}
