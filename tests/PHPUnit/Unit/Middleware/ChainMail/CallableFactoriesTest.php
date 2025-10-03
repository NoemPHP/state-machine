<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Middleware\ChainMail;

use Noem\State\Middleware\ChainMail;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: ChainMail supports supplying callable factories with return type declarations
 */
#[Group('middleware')]
#[Group('chainmail')]
class CallableFactoriesTest extends TestCase
{
    public function testSupportsCallableFactories(): void
    {
        $mail = new ChainMail();

        $factoryCalled = false;
        $mail->supply(function () use (&$factoryCalled): \DateTime {
            $factoryCalled = true;
            return new \DateTime();
        });

        $mail->use(fn(\DateTime $dt) => null);
        $mail->boot();

        $this->assertTrue($factoryCalled);
    }

    public function testRequiresReturnTypeDeclaration(): void
    {
        $mail = new ChainMail();

        // Factory with return type
        $mail->supply(fn(): string => 'value');

        $received = null;
        $mail->use(function (string $str) use (&$received) {
            $received = $str;
        });

        $mail->boot();

        $this->assertEquals('value', $received);
    }

    public function testFactoryCanReturnComplexTypes(): void
    {
        $mail = new ChainMail();

        $mail->supply(fn(): array => ['key' => 'value']);

        $received = null;
        $mail->use(function (array $arr) use (&$received) {
            $received = $arr;
        });

        $mail->boot();

        $this->assertEquals(['key' => 'value'], $received);
    }

    public function testFactoryCanHaveDependencies(): void
    {
        $mail = new ChainMail();

        $mail->supply(fn(): string => 'base');
        $mail->supply(fn(string $base): \stdClass => (object)['value' => $base]);

        $received = null;
        $mail->use(function (\stdClass $obj) use (&$received) {
            $received = $obj;
        });

        $mail->boot();

        $this->assertInstanceOf(\stdClass::class, $received);
        $this->assertEquals('base', $received->value);
    }
}
