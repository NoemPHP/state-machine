<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\Ornament;

use Noem\State\Feature\Async\Ornament;
use Noem\State\Feature\Async\OrnamentResolver;
use Noem\State\Middleware\Mesh;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: OrnamentResolver provides mesh access during resolution
 */
#[Group('async'), Group('ornament-resolution')]
class ResolverProvidesMeshAccessTest extends TestCase
{
    public function testResolverProvidesGetMethod(): void
    {
        $data = ['key' => 'value'];
        $mesh = new Mesh($data);
        $retrievedValue = null;

        new Ornament($mesh, 'computed', function (OrnamentResolver $r) use (&$retrievedValue) {
            $retrievedValue = $r->get('key');
            $r->resolve($retrievedValue);
        });

        $mesh['computed'];

        $this->assertSame('value', $retrievedValue, 'Resolver should provide get() method');
    }

    public function testResolverProvidesHasMethod(): void
    {
        $data = ['existing' => 'value'];
        $mesh = new Mesh($data);
        $hasResults = [];

        new Ornament($mesh, 'computed', function (OrnamentResolver $r) use (&$hasResults) {
            $hasResults['existing'] = $r->has('existing');
            $hasResults['missing'] = $r->has('missing');
            $r->resolve('done');
        });

        $mesh['computed'];

        $this->assertTrue($hasResults['existing'], 'has() should return true for existing key');
        $this->assertFalse($hasResults['missing'], 'has() should return false for missing key');
    }

    public function testResolverGetAccessesMeshData(): void
    {
        $data = ['a' => 1, 'b' => 2, 'c' => 3];
        $mesh = new Mesh($data);

        new Ornament($mesh, 'sum', function (OrnamentResolver $r) {
            $a = $r->get('a');
            $b = $r->get('b');
            $c = $r->get('c');
            $r->resolve($a + $b + $c);
        });

        $result = $mesh['sum'];
        $this->assertSame(6, $result, 'Resolver get() should access mesh data');
    }

    public function testResolverCanAccessMultipleKeys(): void
    {
        $data = ['first' => 'Hello', 'second' => 'World'];
        $mesh = new Mesh($data);

        new Ornament($mesh, 'greeting', function (OrnamentResolver $r) {
            $first = $r->get('first');
            $second = $r->get('second');
            $r->resolve($first . ' ' . $second);
        });

        $result = $mesh['greeting'];
        $this->assertSame('Hello World', $result);
    }

    public function testResolverHasDoesNotThrowForMissingKeys(): void
    {
        $data = [];
        $mesh = new Mesh($data);

        new Ornament($mesh, 'safe', function (OrnamentResolver $r) {
            $exists = $r->has('nonexistent');
            $r->resolve($exists ? 'found' : 'not found');
        });

        $result = $mesh['safe'];
        $this->assertSame('not found', $result);
    }

    public function testResolverGetReturnsNullForMissingKeys(): void
    {
        $data = [];
        $mesh = new Mesh($data);

        new Ornament($mesh, 'nullable', function (OrnamentResolver $r) {
            $value = $r->get('missing');
            $r->resolve($value ?? 'default');
        });

        $result = $mesh['nullable'];
        $this->assertSame('default', $result);
    }
}
