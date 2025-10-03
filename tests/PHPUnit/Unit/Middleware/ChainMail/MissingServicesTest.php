<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Middleware\ChainMail;

use Noem\State\Middleware\ChainException;
use Noem\State\Middleware\ChainMail;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: ChainMail throws ChainException for missing required services
 */
#[Group('middleware')]
#[Group('chainmail')]
class MissingServicesTest extends TestCase
{
    public function testThrowsChainExceptionForMissingRequiredService(): void
    {
        $mail = new ChainMail();

        // Don't supply DateTime service
        $mail->use(function (\DateTime $dateTime) {
            // This should never execute
        });

        $this->expectException(ChainException::class);
        $this->expectExceptionMessage("Service '" . \DateTime::class . "' not found");

        $mail->boot();
    }

    public function testThrowsForMissingServiceEvenWhenOthersAvailable(): void
    {
        $mail = new ChainMail();

        // Supply string but not DateTime
        $mail->supply(fn(): string => 'available');

        $mail->use(function (string $str, \DateTime $dateTime) {
            // This should never execute
        });

        $this->expectException(ChainException::class);
        $this->expectExceptionMessage("Service '" . \DateTime::class . "' not found");

        $mail->boot();
    }

    public function testThrowsForMissingInterfaceService(): void
    {
        $mail = new ChainMail();

        $mail->use(function (\DateTimeInterface $dateTime) {
            // This should never execute
        });

        $this->expectException(ChainException::class);
        $this->expectExceptionMessage("Service '" . \DateTimeInterface::class . "' not found");

        $mail->boot();
    }

    public function testDoesNotThrowWhenAllRequiredServicesAvailable(): void
    {
        $mail = new ChainMail();

        $mail->supply(fn(): \DateTime => new \DateTime());
        $mail->supply(fn(): string => 'value');

        $executed = false;
        $mail->use(function (\DateTime $dt, string $str) use (&$executed) {
            $executed = true;
        });

        $mail->boot();

        $this->assertTrue($executed, 'Callback should execute when all services are available');
    }

    public function testThrowsDescriptiveMessageWithServiceName(): void
    {
        $mail = new ChainMail();

        $customClassName = \stdClass::class;

        $mail->use(function (\stdClass $obj) {
            // This should never execute
        });

        try {
            $mail->boot();
            $this->fail('Expected ChainException to be thrown');
        } catch (ChainException $e) {
            $this->assertStringContainsString($customClassName, $e->getMessage());
            $this->assertStringContainsString('not found', $e->getMessage());
        }
    }

    public function testExceptionThrownDuringBootNotAfter(): void
    {
        $mail = new ChainMail();

        // First boot should fail
        $mail->use(function (\DateTime $dt) {
        });

        $this->expectException(ChainException::class);
        $mail->boot();
    }
}
