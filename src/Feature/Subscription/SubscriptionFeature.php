<?php

declare(strict_types=1);

namespace Noem\State\Feature\Subscription;

use Noem\State\Middleware\ChainMail;
use Noem\State\Chains\Notification;
use Noem\State\Chains\Params\Notify;
use Noem\State\Feature\ExtendedState\ContextChains\BoundAccess;
use Noem\State\Feature\ExtendedState\ContextChains\Params\BoundAccessParams;
use Noem\State\Feature\Feature;
use Noem\State\Util\ParameterDeriver;

/**
 * Filters listeners by parameter type
 *
 * Inspects returned listeners directly and filters based on first parameter
 * type compatibility with the event payload. Uses SplObjectStorage for caching.
 */
class SubscriptionFeature implements Feature
{
    /**
     * @var \SplObjectStorage<callable, string> Listener type cache
     */
    private \SplObjectStorage $typeCache;

    public function __construct()
    {
        $this->typeCache = new \SplObjectStorage();
    }

    public function __invoke(ChainMail $chainMail): void
    {
        $chainMail->use($this->addTypeFiltering(...));
        $chainMail->use($this->bindNotificationChain(...));
    }

    /**
     * Hook notification chain to filter listeners by type
     */
    private function addTypeFiltering(
        Notification $notificationChain
    ): void {
        // Hook chain to filter listeners
        $notificationChain->link(function (Notify $context, callable $next) {
            // Get all listeners from provider/inner middleware
            $allListeners = $next($context);

            // Filter by type compatibility
            return array_filter($allListeners, function ($listener) use ($context) {
                $expectedType = $this->getListenerType($listener);

                // Accept if type matches or is 'object' (catch-all)
                return $expectedType === 'object' || $context->event instanceof $expectedType;
            });
        }, prepend: true);  // Run first to filter before returning
    }

    /**
     * Bind notification chain to BoundAccess for $this->emit() support
     */
    private function bindNotificationChain(?BoundAccess $boundAccess = null): void
    {
        if ($boundAccess === null) {
            return; // ExtendedState not loaded, skip BoundAccess integration
        }

        // Register emit() method on BoundAccess when ExtendedState is enabled
        $boundAccess->link(function (BoundAccessParams $params, callable $next) {
            if ($params->type !== BoundAccessParams::TYPE_METHOD || $params->name !== 'emit') {
                return $next($params); // Not our method, continue chain
            }

            // Handle $this->emit($event) calls
            $event = $params->payload[0] ?? null;
            if ($event === null) {
                throw new \InvalidArgumentException('emit() requires an event object');
            }

            // Get the notification chain for this region and emit the event
            $notifyParams = new Notify($params->region, $event);
            $listeners = $params->region->notificationChain->call($notifyParams);

            // Call all matching listeners
            foreach ($listeners as $listener) {
                $listener($event, $params->region);
            }

            return null;
        }, prepend: true); // Register before default handlers
    }

    /**
     * Get expected type for listener (with caching)
     *
     * Inspects first parameter of callable and caches result.
     *
     * @param callable $listener
     * @return string Class name or 'object'
     */
    private function getListenerType(callable $listener): string
    {
        // Check cache first
        if (isset($this->typeCache[$listener])) {
            return $this->typeCache[$listener];
        }

        // Extract expected event type from first parameter
        try {
            $expectedType = ParameterDeriver::getParameterType($listener, 0);
        } catch (\Throwable) {
            $expectedType = 'object';  // Accept all
        }

        // Cache and return
        $this->typeCache[$listener] = $expectedType;
        return $expectedType;
    }
}
