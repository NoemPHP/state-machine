<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Transitions;

use Noem\State\Feature\Transitions\TransitionRegistry;
use Noem\State\Feature\Transitions\TransitionsFeature;
use Noem\State\Middleware\ChainMail;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: TransitionsFeature registers TransitionRegistry in ChainMail
 */
#[Group('transitions')]
#[Group('feature-registration')]
class RegistersTransitionRegistryTest extends TestCase
{
    public function testRegistersTransitionRegistry(): void
    {
        $chainMail = new ChainMail();
        $feature = new TransitionsFeature();

        $feature($chainMail);

        $registry = $chainMail->get(TransitionRegistry::class);
        $this->assertInstanceOf(TransitionRegistry::class, $registry);
    }
}
