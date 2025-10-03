<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Middleware\Mesh;

use Noem\State\Middleware\Mesh;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Mesh supports custom equality checks for memoization
 */
#[Group('middleware')]
#[Group('mesh')]
class CustomEqualityTest extends TestCase
{
    public function testCustomEqualityCheck(): void
    {
        $callCount = 0;
        $data = [];
        
        $mesh = new Mesh(
            data: $data,
            offsetGet: function ($offset) use (&$callCount) {
                $callCount++;
                return "result-{$offset}";
            }
        );
        
        // Custom equality that treats 'key' and 'KEY' as equal
        $mesh->memoize(function ($a, $b): bool {
            return strtolower($a) === strtolower($b);
        });
        
        $mesh['key'];
        $mesh['KEY']; // Should use cached result from 'key'
        
        $this->assertSame(1, $callCount, 'Custom equality should treat key and KEY as equal');
    }

    public function testCustomEqualityWithObjects(): void
    {
        $callCount = 0;
        $data = [];
        
        $mesh = new Mesh(
            data: $data,
            offsetGet: function ($offset) use (&$callCount) {
                $callCount++;
                return "result-{$offset->id}";
            }
        );
        
        // Custom equality based on object property
        $mesh->memoize(function ($a, $b): bool {
            return is_object($a) && is_object($b) && $a->id === $b->id;
        });
        
        $obj1 = (object)['id' => 1, 'other' => 'data1'];
        $obj2 = (object)['id' => 1, 'other' => 'data2'];
        
        $mesh[$obj1];
        $mesh[$obj2]; // Should use cached result despite different 'other' property
        
        $this->assertSame(1, $callCount);
    }

    public function testDefaultEqualityIsStrict(): void
    {
        $callCount = 0;
        $data = [];
        
        $mesh = new Mesh(
            data: $data,
            offsetGet: function ($offset) use (&$callCount) {
                $callCount++;
                return "result-{$offset}";
            }
        );
        
        // Without custom equality, default is strict (===)
        $mesh->memoize();
        
        $mesh['1'];
        $mesh[1]; // Different types, should not use cache
        
        $this->assertSame(2, $callCount, 'Default equality should be strict');
    }

    public function testCustomEqualityLooseComparison(): void
    {
        $callCount = 0;
        $data = [];
        
        $mesh = new Mesh(
            data: $data,
            offsetGet: function ($offset) use (&$callCount) {
                $callCount++;
                return "result";
            }
        );
        
        // Loose equality check
        $mesh->memoize(function ($a, $b): bool {
            return $a == $b; // Loose comparison
        });
        
        $mesh['1'];
        $mesh[1]; // Should use cached result with loose comparison
        
        $this->assertSame(1, $callCount);
    }

    public function testCustomEqualityWithComplexLogic(): void
    {
        $callCount = 0;
        $data = [];
        
        $mesh = new Mesh(
            data: $data,
            offsetGet: function ($offset) use (&$callCount) {
                $callCount++;
                return "result-{$offset}";
            }
        );
        
        // Consider keys equal if they differ by less than 5
        $mesh->memoize(function ($a, $b): bool {
            if (is_numeric($a) && is_numeric($b)) {
                return abs($a - $b) < 5;
            }
            return $a === $b;
        });
        
        $mesh[10];
        $mesh[12]; // Within 5, should use cache
        $mesh[20]; // More than 5, should not use cache
        
        $this->assertSame(2, $callCount);
    }
}
