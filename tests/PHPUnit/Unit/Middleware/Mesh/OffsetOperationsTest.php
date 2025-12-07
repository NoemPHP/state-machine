<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Middleware\Mesh;

use Noem\State\Middleware\Mesh;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Mesh supports offsetExists, offsetGet, offsetSet, offsetUnset operations
 */
#[Group('middleware')]
#[Group('mesh')]
class OffsetOperationsTest extends TestCase
{
    public function testOffsetExists(): void
    {
        $mesh = new Mesh();

        $mesh['key'] = 'value';

        $this->assertTrue(isset($mesh['key']));
        $this->assertFalse(isset($mesh['non_existent']));
    }

    public function testOffsetGet(): void
    {
        $mesh = new Mesh();

        $mesh['key'] = 'value';

        $this->assertSame('value', $mesh['key']);
    }

    public function testOffsetGetReturnsNullForNonExistent(): void
    {
        $mesh = new Mesh();

        $this->assertNull($mesh['non_existent']);
    }

    public function testOffsetSet(): void
    {
        $mesh = new Mesh();

        $mesh['key'] = 'value';

        $this->assertSame('value', $mesh['key']);
    }

    public function testOffsetUnset(): void
    {
        $mesh = new Mesh();

        $mesh['key'] = 'value';
        $this->assertTrue(isset($mesh['key']));

        unset($mesh['key']);
        $this->assertFalse(isset($mesh['key']));
    }

    public function testAllOperationsTogether(): void
    {
        $mesh = new Mesh();

        // Set
        $mesh['first'] = 'one';
        $mesh['second'] = 'two';

        // Exists
        $this->assertTrue(isset($mesh['first']));
        $this->assertTrue(isset($mesh['second']));

        // Get
        $this->assertSame('one', $mesh['first']);
        $this->assertSame('two', $mesh['second']);

        // Unset
        unset($mesh['first']);
        $this->assertFalse(isset($mesh['first']));
        $this->assertTrue(isset($mesh['second']));
    }

    public function testOffsetSetOverwrite(): void
    {
        $mesh = new Mesh();

        $mesh['key'] = 'original';
        $mesh['key'] = 'updated';

        $this->assertSame('updated', $mesh['key']);
    }
}
