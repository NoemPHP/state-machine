<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Logging;

use Noem\State\Feature\Logging\LogParams;
use Noem\State\Region;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LogParams::class)]
class LogParamsStructureTest extends TestCase
{
    public function testLogParamsContainsLevelMessageAndContext(): void
    {
        $region = $this->createMock(Region::class);
        $region->method('currentState')->willReturn('testState');

        $logParams = new LogParams(
            region: $region,
            level: 'info',
            message: 'Test message',
            context: ['key' => 'value']
        );

        $this->assertSame('info', $logParams->level);
        $this->assertSame('Test message', $logParams->message);
        $this->assertSame(['key' => 'value'], $logParams->context);
    }

    public function testLogParamsAcceptsNullContext(): void
    {
        $region = $this->createMock(Region::class);
        $region->method('currentState')->willReturn('testState');

        $logParams = new LogParams(
            region: $region,
            level: 'debug',
            message: 'Debug message'
        );

        $this->assertNull($logParams->context);
    }
}
