<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Interaction\Registry\Definition;

use Noem\State\Feature\Interaction\InteractionDefinition;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Noem\State\Feature\Interaction\InteractionDefinition
 */
class StoresMetadataTest extends TestCase
{
    public function testStoresMetadataAsReadonlyArrayProperty(): void
    {
        $metadata = [
            'helpText' => 'This will deploy to production servers',
            'defaultValue' => false,
            'placeholder' => 'Type your answer...',
        ];

        $definition = new InteractionDefinition(
            id: 'confirm_deploy',
            type: 'confirm',
            state: 'ready',
            question: 'Deploy?',
            metadata: $metadata
        );

        $this->assertSame($metadata, $definition->metadata);
    }

    public function testMetadataCanBeNull(): void
    {
        $definition = new InteractionDefinition(
            id: 'confirm_deploy',
            type: 'confirm',
            state: 'ready',
            question: 'Deploy?'
        );

        $this->assertNull($definition->metadata);
    }

    public function testMetadataIsReadonly(): void
    {
        $definition = new InteractionDefinition(
            id: 'confirm_deploy',
            type: 'confirm',
            state: 'ready',
            question: 'Deploy?',
            metadata: []
        );

        $reflection = new \ReflectionProperty($definition, 'metadata');
        $this->assertTrue($reflection->isReadOnly());
    }
}
