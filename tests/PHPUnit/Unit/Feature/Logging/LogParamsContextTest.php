<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Logging;

use Noem\State\Feature\Logging\LogParams;
use Noem\State\Region;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LogParams::class)]
class LogParamsContextTest extends TestCase
{
    public function testLogParamsIncludesTimestamp(): void
    {
        $region = $this->createMock(Region::class);
        $region->method('currentState')->willReturn('testState');

        $beforeTime = time();
        $logParams = new LogParams(
            region: $region,
            level: 'info',
            message: 'Test'
        );
        $afterTime = time();

        $this->assertInstanceOf(\DateTimeImmutable::class, $logParams->timestamp);
        $this->assertGreaterThanOrEqual($beforeTime, $logParams->timestamp->getTimestamp());
        $this->assertLessThanOrEqual($afterTime, $logParams->timestamp->getTimestamp());
    }

    public function testLogParamsIncludesCurrentStateName(): void
    {
        $region = $this->createMock(Region::class);
        $region->method('currentState')->willReturn('processingState');

        $logParams = new LogParams(
            region: $region,
            level: 'info',
            message: 'Test'
        );

        $this->assertSame('processingState', $logParams->stateName);
    }

    public function testLogParamsStoresRegionReference(): void
    {
        $region = $this->createMock(Region::class);
        $region->method('currentState')->willReturn('testState');

        $logParams = new LogParams(
            region: $region,
            level: 'info',
            message: 'Test'
        );

        $this->assertSame($region, $logParams->region);
    }
}
