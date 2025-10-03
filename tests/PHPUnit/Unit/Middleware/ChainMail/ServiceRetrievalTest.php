<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Middleware\ChainMail;

use Noem\State\Middleware\ChainException;
use Noem\State\Middleware\ChainMail;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: ChainMail provides get() method for explicit service retrieval
 */
#[Group('middleware')]
#[Group('chainmail')]
class ServiceRetrievalTest extends TestCase
{
    public function testGetMethodRetrievesService(): void
    {
        $mail = new ChainMail();

        $mail->supply(fn(): \DateTime => new \DateTime('2025-01-01'));

        $mail->boot();

        $service = $mail->get(\DateTime::class);

        $this->assertInstanceOf(\DateTime::class, $service);
        $this->assertEquals('2025-01-01', $service->format('Y-m-d'));
    }

    public function testGetMethodWorksBeforeBoot(): void
    {
        $mail = new ChainMail();

        $mail->supply(fn(): string => 'test-value');

        // Don't boot yet
        $service = $mail->get('string');

        $this->assertEquals('test-value', $service);
    }

    public function testGetMethodReturnsSameInstanceOnMultipleCalls(): void
    {
        $mail = new ChainMail();

        $mail->supply(fn(): \DateTime => new \DateTime());

        $mail->boot();

        $first = $mail->get(\DateTime::class);
        $second = $mail->get(\DateTime::class);

        $this->assertSame($first, $second, 'get() should return the same instance');
    }

    public function testGetMethodThrowsForMissingService(): void
    {
        $mail = new ChainMail();

        $this->expectException(ChainException::class);
        $this->expectExceptionMessage("Service '" . \DateTime::class . "' not found");

        $mail->get(\DateTime::class);
    }

    public function testGetMethodWithInterface(): void
    {
        $mail = new ChainMail();

        $mail->supply(fn(): \DateTimeInterface => new \DateTime('2025-01-01'));

        $mail->boot();

        $service = $mail->get(\DateTimeInterface::class);

        $this->assertInstanceOf(\DateTimeInterface::class, $service);
    }

    public function testGetMethodRetrievesComplexTypes(): void
    {
        $mail = new ChainMail();

        $expectedArray = ['key' => 'value', 'nested' => ['data' => 123]];
        $mail->supply(fn(): array => $expectedArray);

        $mail->boot();

        $service = $mail->get('array');

        $this->assertEquals($expectedArray, $service);
    }
}
