<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Logging;

use Noem\State\Feature\Logging\LoggingChain;
use Noem\State\Feature\Logging\LoggingFeature;
use Noem\State\Middleware\ChainMail;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LoggingFeature::class)]
class RegistersChainTest extends TestCase
{
    public function testLoggingFeatureRegistersLoggingChainInMiddlewareStack(): void
    {
        $chainMail = new ChainMail();
        $feature = new LoggingFeature();

        $feature->__invoke($chainMail);

        $loggingChain = $chainMail->get(LoggingChain::class);

        $this->assertInstanceOf(LoggingChain::class, $loggingChain);
    }
}
