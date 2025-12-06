<?php

declare(strict_types=1);

namespace Noem\State\Chains;

use Noem\State\Chains\Params\Notify;
use Noem\State\Middleware\Chain;

/**
 * Chain that manages and resolves global event listeners
 *
 * Stores listeners globally (not per-Region).
 * Provider returns all listeners.
 * Features can filter based on type, priority, etc.
 *
 * @template-extends Chain<Notify,array>
 */
class Notification extends Chain
{
    /**
     * @var list<callable> Global listener storage
     */
    private array $listeners = [];

    public function __construct()
    {
        parent::__construct(
            // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
            provider: function (Notify $context): array {
                // Return ALL listeners globally (parameter required by signature but unused)
                return $this->listeners;
            }
        );
    }

    /**
     * Register a listener globally
     *
     * Stores listener in global array (not per-Region).
     * Listeners receive ($event, $region) when invoked.
     *
     * @param callable $listener Callback receiving (object $event, ?Region $region)
     * @return callable Deregister function
     */
    public function subscribe(callable $listener): callable
    {
        // Add listener to global array
        $this->listeners[] = $listener;

        // Return deregister function
        return function () use ($listener): void {
            $key = array_search($listener, $this->listeners, true);

            if ($key !== false) {
                unset($this->listeners[$key]);
                $this->listeners = array_values($this->listeners); // Re-index
            }
        };
    }
}
