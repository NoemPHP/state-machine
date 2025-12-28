<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Abilities\BuildStep;

use Noem\State\Feature\Abilities\BuildStep\RegisterAbility;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Noem\State\Feature\Abilities\BuildStep\RegisterAbility
 */
final class RegisterAbilityExistsTest extends TestCase
{
    public function testRegisterAbilityClassExists(): void
    {
        $this->assertTrue(
            class_exists(RegisterAbility::class),
            'RegisterAbility BuildStep class must exist'
        );
    }

    public function testImplementsBuildStepInterface(): void
    {
        $handler = fn(array $params) => ['result' => 'test'];

        $step = new RegisterAbility(
            name: 'test-ability',
            handler: $handler
        );

        $this->assertInstanceOf(
            \Noem\State\BuildStep::class,
            $step,
            'RegisterAbility must implement BuildStep interface'
        );
    }
}
