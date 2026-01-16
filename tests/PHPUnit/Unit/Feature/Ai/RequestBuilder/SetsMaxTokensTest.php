<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\RequestBuilder;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: RequestBuilder allows setting max tokens
 */
#[Group('ai'), Group('request-building')]
class SetsMaxTokensTest extends TestCase
{
    #[Test]
    public function setsMaxTokens(): void
    {
        $builder = new \Noem\State\Feature\Ai\RequestBuilder();

        $result = $builder->setMaxTokens(1024);

        $this->assertSame($builder, $result, 'setMaxTokens should return self for fluent interface');
        $this->assertSame(1024, $builder['maxTokens'], 'Max tokens should be set correctly');
    }
}
