<?php

declare(strict_types=1);

namespace Tests\Integration\Feature\Interaction\Registry;

use Noem\State\Feature\Async\AsyncFeature;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\Interaction\ChoiceOption;
use Noem\State\Feature\Interaction\ConfirmRequest;
use Noem\State\Feature\Interaction\InteractionDefinition;
use Noem\State\Feature\Interaction\InteractionFeature;
use Noem\State\Feature\Interaction\InteractionRegistry;
use Noem\State\Feature\Interaction\InteractionRegistryFeature;
use Noem\State\Feature\Interaction\SelectOption;
use Noem\State\Feature\Message\MessageFeature;
use Noem\State\Feature\Subscription\SubscriptionFeature;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Test dispatching interactions by registry ID
 */
class DispatchByIdTest extends TestCase
{
    public function testDispatchConfirmById(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(
            new SubscriptionFeature(),
            new MessageFeature(),
            new ExtendedState(),
            new AsyncFeature(),
            new InteractionFeature(),
            new InteractionRegistryFeature()
        );

        $requestReceived = null;

        $builder->setStates('ready');
        $builder->markInitial('ready');
        $builder->onEnter('ready', function (object $trigger) use (&$requestReceived): \Generator {
            // Dispatch by ID
            yield from $this->interact('deploy_confirm');
        });

        $region = $builder->build();

        // Get registry and register interaction
        $registry = null;
        $builder->chainMail->use(function (?InteractionRegistry $r = null) use (&$registry) {
            $registry = $r;
        });

        $registry->register(new InteractionDefinition(
            id: 'deploy_confirm',
            type: 'confirm',
            state: 'ready',
            question: 'Deploy to production?',
            metadata: ['defaultValue' => false]
        ));

        // Listen for emitted request
        $region->on(function (object $event) use (&$requestReceived) {
            if ($event instanceof ConfirmRequest) {
                $requestReceived = $event;
            }
        });

        $region->trigger((object)[]);

        $this->assertInstanceOf(ConfirmRequest::class, $requestReceived);
        $this->assertSame('Deploy to production?', $requestReceived->question);
        $this->assertFalse($requestReceived->defaultValue);
    }

    public function testDispatchWithRuntimeOverrides(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(
            new SubscriptionFeature(),
            new MessageFeature(),
            new ExtendedState(),
            new AsyncFeature(),
            new InteractionFeature(),
            new InteractionRegistryFeature()
        );

        $requestReceived = null;

        $builder->setStates('ready');
        $builder->markInitial('ready');
        $builder->onEnter('ready', function (object $trigger) use (&$requestReceived): \Generator {
            // Dispatch with runtime override
            yield from $this->interact('backend_select', [
                'context' => 'Server: production-1'
            ]);
        });

        $region = $builder->build();

        // Get registry and register interaction
        $registry = null;
        $builder->chainMail->use(function (?InteractionRegistry $r = null) use (&$registry) {
            $registry = $r;
        });

        $registry->register(new InteractionDefinition(
            id: 'backend_select',
            type: 'select',
            state: 'ready',
            question: 'Choose backend',
            options: [
                'mysql' => ['label' => 'MySQL', 'description' => 'Traditional'],
                'postgres' => ['label' => 'PostgreSQL', 'description' => 'Advanced'],
            ]
        ));

        // Listen for emitted request
        $region->on(function (object $event) use (&$requestReceived) {
            $requestReceived = $event;
        });

        $region->trigger((object)[]);

        $this->assertSame('Choose backend', $requestReceived->question);
        $this->assertSame('Server: production-1', $requestReceived->context);
        $this->assertArrayHasKey('mysql', $requestReceived->options);
        $this->assertInstanceOf(SelectOption::class, $requestReceived->options['mysql']);
    }

    public function testThrowsIfRegistryNotAvailable(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(
            new SubscriptionFeature(),
            new MessageFeature(),
            new ExtendedState(),
            new AsyncFeature(),
            new InteractionFeature()
            // Note: InteractionRegistryFeature NOT loaded
        );

        $builder->setStates('ready');
        $builder->markInitial('ready');
        $builder->onEnter('ready', function (object $trigger): \Generator {
            yield from $this->interact('some_id');
        });

        $region = $builder->build();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('InteractionRegistry not available');

        $region->trigger((object)[]);
    }

    public function testThrowsIfDefinitionNotFound(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(
            new SubscriptionFeature(),
            new MessageFeature(),
            new ExtendedState(),
            new AsyncFeature(),
            new InteractionFeature(),
            new InteractionRegistryFeature()
        );

        $builder->setStates('ready');
        $builder->markInitial('ready');
        $builder->onEnter('ready', function (object $trigger): \Generator {
            yield from $this->interact('nonexistent_id');
        });

        $region = $builder->build();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Interaction definition 'nonexistent_id' not found");

        $region->trigger((object)[]);
    }
}
