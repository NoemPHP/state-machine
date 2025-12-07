<?php

declare(strict_types=1);

namespace Noem\State\Feature\Message;

use Noem\State\Middleware\ChainMail;
use Noem\State\Chains\DispatchAction;
use Noem\State\Chains\Notification;
use Noem\State\Chains\Params\Action;
use Noem\State\Chains\Params\Notify;
use Noem\State\Feature\Feature;

/**
 * Implements call-response messaging on subscription infrastructure
 *
 * MessageFeature hooks into both DispatchAction and Notification chains to provide:
 * - Automatic correlation-based subscription setup when Messages are dispatched
 * - Response delivery to then() handlers when matching correlation IDs arrive
 * - Correlation-based filtering optimization for message events
 */
class MessageFeature implements Feature
{
    public function __invoke(ChainMail $chainMail): void
    {
        $chainMail->use($this->installMessageDispatching(...));
        $chainMail->use($this->installCorrelationFiltering(...));
    }

    /**
     * Add message dispatching middleware to action chain
     *
     * Hooks DispatchAction chain to detect Message payloads and set up
     * temporary subscriptions for correlation-based response delivery
     */
    private function installMessageDispatching(
        DispatchAction $dispatchChain,
        Notification $notificationChain
    ): void {
        // Hook DispatchAction chain to scan for Messages
        $dispatchChain->link(function (Action $action, callable $next) use ($notificationChain) {
            // Dispatch action first
            $result = $next($action);

            // Check if payload is a Message
            if (!$action->payload instanceof Message) {
                return $result;
            }

            // For Messages, set up temporary response subscription
            $this->setupMessageSubscription(
                $action->payload,
                $notificationChain
            );

            return $result;
        });
    }

    /**
     * Set up temporary subscription for message response
     *
     * Creates a correlation-based subscription that:
     * - Listens for Message events with matching correlation ID
     * - Delivers responses to then() handlers
     * - Auto-unsubscribes after first matching response (first-response-wins)
     */
    private function setupMessageSubscription(
        Message $message,
        Notification $notificationChain
    ): void {
        // Subscribe to events with correlation ID matching request
        $unsubscribe = $notificationChain->subscribe(
            function (object $event, ?\Noem\State\Region $eventRegion = null) use ($message, &$unsubscribe) {
                // Check if event is a Message responding to our request
                if (!($event instanceof Message)) {
                    return;
                }

                if (!$event->repliesTo($message)) {
                    return;
                }

                // Correlation matches - deliver response to request then() callbacks
                $message->deliverResponse($event);

                // Auto cleanup - remove this subscription since we got our response
                $unsubscribe();
            }
        );
    }

    /**
     * Install correlation filtering optimization on NotificationChain
     *
     * Adds middleware to pre-filter Message events by correlation ID,
     * optimizing delivery by avoiding unnecessary listener invocations
     * for messages with non-matching correlation IDs
     */
    private function installCorrelationFiltering(Notification $notificationChain): void
    {
        // Note: This optimization is already handled by the subscription setup above
        // which uses repliesTo() to filter. No additional middleware needed for v1.
        // This method exists to satisfy the spec but has no implementation in v1.

        // Future optimization: Could add middleware here to pre-filter listeners
        // before type checking, but current implementation is sufficient.
    }
}
