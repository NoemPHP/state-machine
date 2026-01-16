<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\RequestBuilder;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: RequestBuilder builds immutable Request object
 */
#[Group('ai'), Group('request-building')]
class BuildsRequestTest extends TestCase
{
    #[Test]
    public function buildsRequest(): void
    {
        $builder = new \Noem\State\Feature\Ai\RequestBuilder();
        $responseFormat = new \Noem\State\Feature\Ai\ResponseFormat('json', ['type' => 'object']);

        $builder->setModel('gpt-4')
            ->setPrompt('Test prompt')
            ->setMaxTokens(512)
            ->setTemperature(0.7)
            ->setStop('###')
            ->setResponseFormat($responseFormat);

        $request = $builder->build();

        $this->assertInstanceOf(\Noem\State\Feature\Ai\Request::class, $request);
        $this->assertSame('gpt-4', $request->model);
        $this->assertSame('Test prompt', $request->prompt);
        $this->assertSame(512, $request->maxTokens);
        $this->assertSame(0.7, $request->temperature);
        $this->assertSame('###', $request->stop);
        $this->assertSame($responseFormat, $request->responseFormat);
    }
}
