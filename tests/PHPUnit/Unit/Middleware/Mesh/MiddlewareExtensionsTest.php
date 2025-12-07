<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Middleware\Mesh;

use Noem\State\Middleware\Mesh;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Mesh allows middleware-style extensions for all array operations
 */
#[Group('middleware')]
#[Group('mesh')]
class MiddlewareExtensionsTest extends TestCase
{
    public function testExtendOffsetGet(): void
    {
        $mesh = new Mesh();
        $mesh['key'] = 'original';

        $mesh->extend(
            offsetGet: function (mixed $offset, callable $next): mixed {
                $value = $next($offset);
                return $value ? strtoupper($value) : null;
            }
        );

        $this->assertSame('ORIGINAL', $mesh['key']);
    }

    public function testExtendOffsetSet(): void
    {
        $mesh = new Mesh();

        $mesh->extend(
            offsetSet: function (object $context, callable $next): void {
                $context->value = 'prefix-' . $context->value;
                $next($context);
            }
        );

        $mesh['key'] = 'value';

        $this->assertSame('prefix-value', $mesh['key']);
    }

    public function testExtendOffsetExists(): void
    {
        $mesh = new Mesh();

        $mesh->extend(
            offsetExists: function (mixed $offset, callable $next): bool {
                // Make 'magic' key always exist
                if ($offset === 'magic') {
                    return true;
                }
                return $next($offset);
            }
        );

        $this->assertTrue(isset($mesh['magic']));
        $this->assertFalse(isset($mesh['other']));
    }

    public function testExtendOffsetUnset(): void
    {
        $mesh = new Mesh();
        $mesh['protected'] = 'value';
        $mesh['normal'] = 'value';

        $mesh->extend(
            offsetUnset: function (mixed $offset, callable $next): void {
                // Protect certain keys from being unset
                if ($offset !== 'protected') {
                    $next($offset);
                }
            }
        );

        unset($mesh['protected']);
        unset($mesh['normal']);

        $this->assertTrue(isset($mesh['protected']));
        $this->assertFalse(isset($mesh['normal']));
    }

    public function testMultipleMiddlewareExtensions(): void
    {
        $mesh = new Mesh();
        $mesh['value'] = 10;

        // First middleware: multiply by 2
        $mesh->extend(
            offsetGet: function (mixed $offset, callable $next): mixed {
                $value = $next($offset);
                return is_numeric($value) ? $value * 2 : $value;
            }
        );

        // Second middleware: add 5
        $mesh->extend(
            offsetGet: function (mixed $offset, callable $next): mixed {
                $value = $next($offset);
                return is_numeric($value) ? $value + 5 : $value;
            }
        );

        // Chains execute in reverse order (last linked first)
        // So: second adds 5 first (10 + 5 = 15), then first multiplies by 2 (15 * 2 = 30)
        $this->assertSame(30, $mesh['value']);
    }

    public function testExtendAllOperations(): void
    {
        $mesh = new Mesh();
        $log = [];

        $mesh->extend(
            offsetExists: function (mixed $offset, callable $next) use (&$log): bool {
                $log[] = "exists:{$offset}";
                return $next($offset);
            },
            offsetGet: function (mixed $offset, callable $next) use (&$log): mixed {
                $log[] = "get:{$offset}";
                return $next($offset);
            },
            offsetSet: function (object $context, callable $next) use (&$log): void {
                $log[] = "set:{$context->offset}";
                $next($context);
            },
            offsetUnset: function (mixed $offset, callable $next) use (&$log): void {
                $log[] = "unset:{$offset}";
                $next($offset);
            }
        );

        $mesh['test'] = 'value';
        $exists = isset($mesh['test']);
        $value = $mesh['test'];
        unset($mesh['test']);

        $this->assertContains('set:test', $log);
        $this->assertContains('exists:test', $log);
        $this->assertContains('get:test', $log);
        $this->assertContains('unset:test', $log);
    }
}
