<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\RequestBuilder;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: RequestBuilder allows setting stop sequences
 */
#[Group('ai'), Group('request-building')]
class SetsStopSequenceTest extends TestCase
{
    #[Test]
    public function setsStopSequence(): void
    {
        $builder = new \Noem\State\Feature\Ai\RequestBuilder();

        $result = $builder->setStop('\n\n');

        $this->assertSame($builder, $result, 'setStop should return self for fluent interface');
        $this->assertSame('\n\n', $builder['stop'], 'Stop sequence should be set correctly');
    }
}
