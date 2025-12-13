<?php

declare(strict_types=1);

namespace Noem\State\Callbacks;

use Noem\State\Region;

/**
 * Centralized storage and querying for typed callbacks.
 *
 * Provides centralized callback storage accessible to all features, enabling
 * cross-cutting callback management with precise querying by region, type,
 * event, and state.
 */
class CallbackRegistry
{
    /**
     * @var array<int, CallbackRecord>
     */
    private array $records = [];

    /**
     * Register a callback record in the registry.
     *
     * @param CallbackRecord $record The callback record to register
     */
    public function register(CallbackRecord $record): void
    {
        $this->records[] = $record;
    }

    /**
     * Query callbacks with optional filtering.
     *
     * @param Region|null $region Filter by region instance
     * @param CallbackType|null $type Filter by callback type
     * @param string|null $event Filter by event name (action, enter, exit)
     * @param string|null $state Filter by state name
     * @return array<int, CallbackRecord> Matching records in insertion order
     */
    public function query(
        ?Region $region = null,
        ?CallbackType $type = null,
        ?string $event = null,
        ?string $state = null
    ): array {
        $results = $this->records;

        if ($region !== null) {
            $results = array_filter(
                $results,
                fn(CallbackRecord $record) => $record->region === $region
            );
        }

        if ($type !== null) {
            $results = array_filter(
                $results,
                fn(CallbackRecord $record) => $type->is($record->type)
            );
        }

        if ($event !== null) {
            $results = array_filter(
                $results,
                fn(CallbackRecord $record) => $record->event === $event
            );
        }

        if ($state !== null) {
            $results = array_filter(
                $results,
                fn(CallbackRecord $record) => $record->state === $state
            );
        }

        // Re-index to return sequential array
        return array_values($results);
    }
}
