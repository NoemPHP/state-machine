<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Middleware\ChainMail;

use Noem\State\Middleware\ChainMail;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: ChainMail provides dependency injection container functionality
 */
#[Group('middleware')]
#[Group('chainmail')]
class DependencyInjectionTest extends TestCase
{
    public function testProvidesDependencyInjection(): void
    {
        $mail = new ChainMail();

        $mail->supply(fn(): \DateTime => new \DateTime('2025-01-01'));

        $received = null;
        $mail->use(function (\DateTime $dateTime) use (&$received) {
            $received = $dateTime;
        });

        $mail->boot();

        $this->assertInstanceOf(\DateTime::class, $received);
        $this->assertEquals('2025-01-01', $received->format('Y-m-d'));
    }

    public function testInjectsMultipleDependencies(): void
    {
        $mail = new ChainMail();

        $mail->supply(fn(): \DateTime => new \DateTime('2025-01-01'));
        $mail->supply(fn(): string => 'test-value');

        $receivedDate = null;
        $receivedString = null;

        $mail->use(function (\DateTime $date, string $str) use (&$receivedDate, &$receivedString) {
            $receivedDate = $date;
            $receivedString = $str;
        });

        $mail->boot();

        $this->assertInstanceOf(\DateTime::class, $receivedDate);
        $this->assertEquals('test-value', $receivedString);
    }

    public function testSupportsInterfaceBasedInjection(): void
    {
        $mail = new ChainMail();

        $mail->supply(fn(): \DateTimeInterface => new \DateTime('2025-01-01'));

        $received = null;
        $mail->use(function (\DateTimeInterface $dateTime) use (&$received) {
            $received = $dateTime;
        });

        $mail->boot();

        $this->assertInstanceOf(\DateTimeInterface::class, $received);
    }
}
