<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Includes;

use Noem\State\Feature\Includes\Chains\LoadFile;
use Noem\State\Feature\Includes\IncludesFeature;
use Noem\State\Middleware\ChainMail;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: IncludesFeature registers LoadFile chain in ChainMail
 */
#[Group('includes')]
#[Group('feature-registration')]
class RegistersLoadFileChainTest extends TestCase
{
    public function testRegistersLoadFileChain(): void
    {
        $chainMail = new ChainMail();
        $feature = new IncludesFeature();

        $feature($chainMail);

        $loadFile = $chainMail->get(LoadFile::class);
        $this->assertInstanceOf(LoadFile::class, $loadFile);
    }
}
