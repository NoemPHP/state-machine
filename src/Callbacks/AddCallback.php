<?php

declare(strict_types=1);

namespace Noem\State\Callbacks;

use Noem\State\BuildStep;
use Noem\State\Region;
use Noem\State\RegionBuilder;

/**
 * BuildStep for declarative callback registration.
 *
 * Integrates callback registration into the fluent builder pipeline, enabling
 * declarative configuration. Registers callbacks in the centralized CallbackRegistry
 * during the build process.
 */
final class AddCallback implements BuildStep
{
    private const VALID_EVENTS = ['action', 'enter', 'exit'];

    public function __construct(
        private readonly string $event,
        private readonly string $state,
        private readonly \Closure $callback,
        private readonly ?CallbackType $type = null,
        private readonly mixed $metadata = null,
    ) {
        if (!in_array($event, self::VALID_EVENTS, true)) {
            throw new \InvalidArgumentException(
                sprintf(
                    "Event must be one of %s, got '%s'",
                    implode(', ', self::VALID_EVENTS),
                    $event
                )
            );
        }
    }

    public function callback(RegionBuilder $builder, callable $next, callable $first): Region
    {
        $region = $next($builder);

        // Retrieve CallbackRegistry from ChainMail
        $registry = $builder->chainMail->get(CallbackRegistry::class);

        $record = new CallbackRecord(
            region: $region,
            type: $this->type ?? DefaultCallbackType::get(),
            event: $this->event,
            state: $this->state,
            callback: $this->callback,
            metadata: $this->metadata
        );

        $registry->register($record);

        return $region;
    }
}
