<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Middleware\Mesh;

use Noem\State\Middleware\Mesh;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Mesh supports extending with other arrays or ArrayAccess objects
 */
#[Group('middleware')]
#[Group('mesh')]
class ExtendWithTest extends TestCase
{
    public function testExtendWithArray(): void
    {
        $mesh = new Mesh();
        $extension = ['extendedKey' => 'extendedValue'];

        $mesh->extendWith($extension);

        $this->assertTrue(isset($mesh['extendedKey']));
        $this->assertSame('extendedValue', $mesh['extendedKey']);
    }

    public function testExtendWithArrayAccess(): void
    {
        $mesh = new Mesh();
        $arrayObject = new \ArrayObject(['key' => 'value']);

        $mesh->extendWith($arrayObject);

        $this->assertTrue(isset($mesh['key']));
        $this->assertSame('value', $mesh['key']);
    }

    public function testExtendWithPreservesOriginalData(): void
    {
        $mesh = new Mesh();
        $mesh['original'] = 'data';

        $extension = ['extended' => 'value'];
        $mesh->extendWith($extension);

        $this->assertSame('data', $mesh['original']);
        $this->assertSame('value', $mesh['extended']);
    }

    public function testExtendWithPriorityOverOriginal(): void
    {
        $mesh = new Mesh();
        $mesh['key'] = 'original';

        $extension = ['key' => 'extended'];
        $mesh->extendWith($extension);

        // Extension should have priority
        $this->assertSame('extended', $mesh['key']);
    }

    public function testExtendWithMultipleExtensions(): void
    {
        $mesh = new Mesh();

        $firstExtension = ['first' => 'one'];
        $secondExtension = ['second' => 'two'];

        $mesh->extendWith($firstExtension);
        $mesh->extendWith($secondExtension);

        $this->assertSame('one', $mesh['first']);
        $this->assertSame('two', $mesh['second']);
    }

    public function testExtendWithModifiesExtension(): void
    {
        $mesh = new Mesh();
        $extension = [];

        $mesh->extendWith($extension);
        $mesh['newKey'] = 'newValue';

        // The extension should be modified too
        $this->assertArrayHasKey('newKey', $extension);
        $this->assertSame('newValue', $extension['newKey']);
    }

    public function testExtendWithArrayObject(): void
    {
        $mesh = new Mesh();
        $arrayObject = new \ArrayObject();

        $mesh->extendWith($arrayObject);
        $mesh['test'] = 'value';

        $this->assertSame('value', $arrayObject['test']);
    }
}
