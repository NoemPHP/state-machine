<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Middleware\Mesh;

use Noem\State\Middleware\Mesh;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Mesh supports middleware chains for each array operation (get, set, exists, unset)
 */
#[Group('middleware')]
#[Group('mesh')]
class OperationChainsTest extends TestCase
{
    public function testGetOperationChain(): void
    {
        $mesh = new Mesh();
        $mesh['value'] = 'original';

        // Chain 1: Add prefix
        $mesh->extend(
            offsetGet: function ($offset, $next) {
                return 'prefix-' . $next($offset);
            }
        );

        // Chain 2: Add suffix
        $mesh->extend(
            offsetGet: function ($offset, $next) {
                return $next($offset) . '-suffix';
            }
        );

        $this->assertSame('prefix-original-suffix', $mesh['value']);
    }

    public function testSetOperationChain(): void
    {
        $mesh = new Mesh();

        // Chain 1: Convert to uppercase
        $mesh->extend(
            offsetSet: function ($context, $next) {
                $context->value = strtoupper($context->value);
                $next($context);
            }
        );

        // Chain 2: Add timestamp
        $mesh->extend(
            offsetSet: function ($context, $next) {
                $context->value = $context->value . '-MODIFIED';
                $next($context);
            }
        );

        $mesh['test'] = 'value';

        $this->assertSame('VALUE-MODIFIED', $mesh['test']);
    }

    public function testExistsOperationChain(): void
    {
        $mesh = new Mesh();
        $mesh['real'] = 'value';

        // Chain 1: Make 'virtual' always exist
        $mesh->extend(
            offsetExists: function ($offset, $next) {
                if ($offset === 'virtual') {
                    return true;
                }
                return $next($offset);
            }
        );

        // Chain 2: Make 'ghost' always exist
        $mesh->extend(
            offsetExists: function ($offset, $next) {
                if ($offset === 'ghost') {
                    return true;
                }
                return $next($offset);
            }
        );

        $this->assertTrue(isset($mesh['real']));
        $this->assertTrue(isset($mesh['virtual']));
        $this->assertTrue(isset($mesh['ghost']));
        $this->assertFalse(isset($mesh['other']));
    }

    public function testUnsetOperationChain(): void
    {
        $mesh = new Mesh();
        $mesh['protected1'] = 'value1';
        $mesh['protected2'] = 'value2';
        $mesh['normal'] = 'value3';

        // Chain 1: Protect 'protected1'
        $mesh->extend(
            offsetUnset: function ($offset, $next) {
                if ($offset !== 'protected1') {
                    $next($offset);
                }
            }
        );

        // Chain 2: Protect 'protected2'
        $mesh->extend(
            offsetUnset: function ($offset, $next) {
                if ($offset !== 'protected2') {
                    $next($offset);
                }
            }
        );

        unset($mesh['protected1']);
        unset($mesh['protected2']);
        unset($mesh['normal']);

        $this->assertTrue(isset($mesh['protected1']));
        $this->assertTrue(isset($mesh['protected2']));
        $this->assertFalse(isset($mesh['normal']));
    }

    public function testChainsWorkTogether(): void
    {
        $mesh = new Mesh();
        $log = [];

        // Set chain logs
        $mesh->extend(
            offsetSet: function ($context, $next) use (&$log) {
                $log[] = 'set-start';
                $next($context);
                $log[] = 'set-end';
            }
        );

        // Get chain logs
        $mesh->extend(
            offsetGet: function ($offset, $next) use (&$log) {
                $log[] = 'get-start';
                $result = $next($offset);
                $log[] = 'get-end';
                return $result;
            }
        );

        $mesh['test'] = 'value';
        $value = $mesh['test'];

        $this->assertContains('set-start', $log);
        $this->assertContains('set-end', $log);
        $this->assertContains('get-start', $log);
        $this->assertContains('get-end', $log);
    }

    public function testChainOrderMatters(): void
    {
        $mesh = new Mesh();
        $mesh['value'] = 10;

        // First: multiply by 2
        $mesh->extend(
            offsetGet: function ($offset, $next) {
                $value = $next($offset);
                return is_numeric($value) ? $value * 2 : $value;
            }
        );

        // Second: add 10
        $mesh->extend(
            offsetGet: function ($offset, $next) {
                $value = $next($offset);
                return is_numeric($value) ? $value + 10 : $value;
            }
        );

        // Chains execute in reverse order (last linked first)
        // So: second adds 10 first (10 + 10 = 20), then first multiplies by 2 (20 * 2 = 40)
        $this->assertSame(40, $mesh['value']);
    }
}
