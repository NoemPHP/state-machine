<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\RequestBuilder;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: RequestBuilder allows setting response format
 */
#[Group('ai'), Group('request-building')]
class SetsResponseFormatTest extends TestCase
{
    #[Test]
    public function setsResponseFormat(): void
    {
        $builder = new \Noem\State\Feature\Ai\RequestBuilder();
        $responseFormat = new \Noem\State\Feature\Ai\ResponseFormat('json', ['type' => 'object']);

        $result = $builder->setResponseFormat($responseFormat);

        $this->assertSame($builder, $result, 'setResponseFormat should return self for fluent interface');
        $this->assertSame($responseFormat, $builder['responseFormat'], 'ResponseFormat should be set correctly');
    }
}
