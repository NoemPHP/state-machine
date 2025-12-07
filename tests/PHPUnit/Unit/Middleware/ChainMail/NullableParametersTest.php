<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Middleware\ChainMail;

use Noem\State\Middleware\ChainMail;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: ChainMail supports nullable parameters when service is not available
 */
#[Group('middleware')]
#[Group('chainmail')]
class NullableParametersTest extends TestCase
{
    public function testNullableParameterReceivesNullWhenServiceNotAvailable(): void
    {
        $mail = new ChainMail();

        $received = 'not-null';
        $mail->use(function (?\DateTime $dateTime) use (&$received) {
            $received = $dateTime;
        });

        $mail->boot();

        $this->assertNull($received);
    }

    public function testNullableParameterReceivesServiceWhenAvailable(): void
    {
        $mail = new ChainMail();

        $mail->supply(fn(): \DateTime => new \DateTime('2025-01-01'));

        $received = null;
        $mail->use(function (?\DateTime $dateTime) use (&$received) {
            $received = $dateTime;
        });

        $mail->boot();

        $this->assertInstanceOf(\DateTime::class, $received);
        $this->assertEquals('2025-01-01', $received->format('Y-m-d'));
    }

    public function testMultipleNullableParameters(): void
    {
        $mail = new ChainMail();

        // Only supply one of two services
        $mail->supply(fn(): string => 'available');

        $receivedString = null;
        $receivedDateTime = 'not-null';

        $mail->use(function (string $str, ?\DateTime $dt) use (&$receivedString, &$receivedDateTime) {
            $receivedString = $str;
            $receivedDateTime = $dt;
        });

        $mail->boot();

        $this->assertEquals('available', $receivedString);
        $this->assertNull($receivedDateTime);
    }

    public function testMixedNullableAndRequiredParameters(): void
    {
        $mail = new ChainMail();

        $mail->supply(fn(): string => 'required');
        // DateTime and stdClass not supplied

        $receivedString = null;
        $receivedDateTime = 'not-null';
        $receivedStdClass = 'not-null';

        $mail->use(function (
            string $str,
            ?\DateTime $dt,
            ?\stdClass $obj
        ) use (
            &$receivedString,
            &$receivedDateTime,
            &$receivedStdClass
        ) {
            $receivedString = $str;
            $receivedDateTime = $dt;
            $receivedStdClass = $obj;
        });

        $mail->boot();

        $this->assertEquals('required', $receivedString);
        $this->assertNull($receivedDateTime);
        $this->assertNull($receivedStdClass);
    }
}
