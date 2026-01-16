<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\RequestBuilder;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: RequestBuilder allows setting temperature
 */
#[Group('ai'), Group('request-building')]
class SetsTemperatureTest extends TestCase
{
    #[Test]
    public function setsTemperature(): void
    {
        $builder = new \Noem\State\Feature\Ai\RequestBuilder();

        $result = $builder->setTemperature(0.8);

        $this->assertSame($builder, $result, 'setTemperature should return self for fluent interface');
        $this->assertSame(0.8, $builder['temperature'], 'Temperature should be set correctly');
    }
}
