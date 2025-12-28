<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\RequestBuilder;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: RequestBuilder constructs Request with default configuration
 */
#[Group('ai'), Group('request-building')]
class ConstructsWithDefaultsTest extends TestCase
{
    #[Test]
    public function constructsWithDefaults(): void
    {
        $builder = new \Noem\State\Feature\Ai\RequestBuilder();
        $request = $builder->build();

        $this->assertInstanceOf(\Noem\State\Feature\Ai\Request::class, $request);
        $this->assertSame('http://telvanni:7863/v1', $request->baseUrl);
        $this->assertSame('sk-111111111111111111111111111111111111111111111111', $request->token);
        $this->assertNotEmpty($request->model);
        $this->assertSame('say hello', $request->prompt);
        $this->assertSame(2048, $request->maxTokens);
        $this->assertSame(0.6, $request->temperature);
        $this->assertNull($request->stop);
        $this->assertTrue($request->logprobs);
        $this->assertTrue($request->stream);
    }
}
