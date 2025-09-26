<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Middleware;

use Noem\State\Middleware\ChainMail;
use Noem\State\Middleware\Overlay;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertInstanceOf;

#[Group('middleware')]

class ChainMailTest extends TestCase
{

    #[Test] public function factory()
    {
        $chain = new ChainMail();
        $currentDate = date_create();
        $currentTimeString = $currentDate->format('Y-m-d H:i');
        $chain
            ->supply(
                fn(): \DateTime => date_create()
            )->use(function (\DateTime $dateTime) use ($currentTimeString) {
                $this->assertEquals($currentTimeString, $dateTime->format('Y-m-d H:i'));
            })->boot();
    }

    #[Test] public function overlay()
    {
        $chain = new ChainMail();
        $currentDate = date_create_immutable();
        $interval = \DateInterval::createFromDateString('1 day');
        $currentTimeString = $currentDate->add($interval)->format('Y-m-d H:i');
        $chain
            ->supply(
                fn(): \DateTimeImmutable => date_create_immutable(),
                #[Overlay] function (callable $next) use ($interval): \DateTimeImmutable {
                    $result = $next();
                    $this->assertInstanceOf(\DateTimeImmutable::class, $result);

                    return $result->add($interval);
                }
            )->use(function (\DateTimeImmutable $dateTime) use ($currentTimeString) {
                $this->assertEquals($currentTimeString, $dateTime->format('Y-m-d H:i'));
            })->boot();
    }

    #[Test] public function memo()
    {
        $chain = new ChainMail();
        $currentDate = date_create();
        $currentTimeString = $currentDate->format('Y-m-d H:i');
        $times = 0;
        $chain
            ->supply(
                function () use (&$times): \DateTime {
                    $times++;

                    return date_create();
                }
            )->use(function (\DateTime $dateTime) use ($currentTimeString) {
                $this->assertEquals($currentTimeString, $dateTime->format('Y-m-d H:i'));
            })->use(function (\DateTime $dateTime) use ($currentTimeString) {
                $this->assertEquals($currentTimeString, $dateTime->format('Y-m-d H:i'));
            })->use(function (\DateTime $dateTime) use ($currentTimeString) {
                $this->assertEquals($currentTimeString, $dateTime->format('Y-m-d H:i'));
            })->boot();
        $this->assertEquals(1, $times);
    }
}
