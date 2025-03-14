<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Middleware;

use Noem\State\Middleware\ChainMail;
use PHPUnit\Framework\TestCase;

class ChainMailTest extends TestCase
{

    public function testSimpleChainmail()
    {
        $chain = new ChainMail();
        $currentDate = date_create();
        $currentTimeString = $currentDate->format('Y-m-d H:i');
        $chain->use(function (\DateTime $dateTime) use ($currentTimeString) {
            $this->assertEquals($currentTimeString, $dateTime->format('Y-m-d H:i'));
        },
            fn(): \DateTime => date_create()
        );
    }
}
