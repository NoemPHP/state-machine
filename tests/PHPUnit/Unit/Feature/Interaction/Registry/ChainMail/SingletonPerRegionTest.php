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
class SingletonPerRegionTest extends TestCase
{
    public function testSingleInteractionRegistryInstanceSharedAcrossEntireRegion(): void
    {
        $chainMail = new ChainMail();
        $feature = new InteractionRegistryFeature();

        $feature($chainMail);

        $registry1 = null;
        $registry2 = null;

        $chainMail->use(function (?InteractionRegistry $r = null) use (&$registry1) {
            $registry1 = $r;
        });

        $chainMail->use(function (?InteractionRegistry $r = null) use (&$registry2) {
            $registry2 = $r;
        });

        $this->assertSame($registry1, $registry2);
    }
}
