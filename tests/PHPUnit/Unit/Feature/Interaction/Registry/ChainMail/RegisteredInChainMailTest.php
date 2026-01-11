<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Interaction\Registry\ChainMail;

use Noem\State\Feature\Interaction\InteractionRegistry;
use Noem\State\Feature\Interaction\InteractionRegistryFeature;
use Noem\State\Middleware\ChainMail;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InteractionRegistryFeature::class)]
#[CoversClass(InteractionRegistry::class)]
class RegisteredInChainMailTest extends TestCase
{
    public function testInteractionRegistryRegisteredInChainMailDuringFeatureInitialization(): void
    {
        $chainMail = new ChainMail();
        $feature = new InteractionRegistryFeature();

        $feature($chainMail);
        $chainMail->boot();

        // Attempt to retrieve registry from ChainMail
        $registry = null;
        $chainMail->use(function (?InteractionRegistry $r = null) use (&$registry) {
            $registry = $r;
        });

        $this->assertInstanceOf(InteractionRegistry::class, $registry);
    }
}
