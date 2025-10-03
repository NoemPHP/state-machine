<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Middleware\ChainMail;

use Noem\State\Middleware\ChainMail;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: ChainMail supports lazy service resolution (services created on first access)
 */
#[Group('middleware')]
#[Group('chainmail')]
class LazyServiceResolutionTest extends TestCase
{
    public function testServicesCreatedLazily(): void
    {
        $mail = new ChainMail();

        $created = false;
        $mail->supply(function () use (&$created): string {
            $created = true;
            return 'value';
        });

        // Service should not be created yet
        $this->assertFalse($created);

        $mail->use(fn(string $str) => null);
        $this->assertFalse($created, 'Service should not be created before boot');

        $mail->boot();
        $this->assertTrue($created, 'Service should be created on first access during boot');
    }

    public function testUnusedServicesNotCreated(): void
    {
        $mail = new ChainMail();

        $unusedCreated = false;
        $usedCreated = false;

        $mail->supply(function () use (&$unusedCreated): int {
            $unusedCreated = true;
            return 42;
        });

        $mail->supply(function () use (&$usedCreated): string {
            $usedCreated = true;
            return 'used';
        });

        $mail->use(fn(string $str) => null);
        $mail->boot();

        $this->assertTrue($usedCreated);
        $this->assertFalse($unusedCreated, 'Unused service should not be created');
    }

    public function testServiceCreatedOnlyOnce(): void
    {
        $mail = new ChainMail();

        $creationCount = 0;
        $mail->supply(function () use (&$creationCount): \DateTime {
            $creationCount++;
            return new \DateTime();
        });

        $mail->use(fn(\DateTime $dt1) => null);
        $mail->use(fn(\DateTime $dt2) => null);
        $mail->use(fn(\DateTime $dt3) => null);

        $mail->boot();

        $this->assertEquals(1, $creationCount, 'Service should only be created once despite multiple usages');
    }
}
