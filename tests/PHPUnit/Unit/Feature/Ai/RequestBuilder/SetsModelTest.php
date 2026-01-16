<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\RequestBuilder;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: RequestBuilder allows setting model
 */
#[Group('ai'), Group('request-building')]
class SetsModelTest extends TestCase
{
    #[Test]
    public function setsModel(): void
    {
        $builder = new \Noem\State\Feature\Ai\RequestBuilder();

        $result = $builder->setModel('gpt-4');

        $this->assertSame($builder, $result, 'setModel should return self for fluent interface');
        $this->assertSame('gpt-4', $builder['model'], 'Model should be set correctly');
    }
}
