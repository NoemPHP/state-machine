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
    /**
     * Tracks messages that already have subscriptions set up to prevent duplicates
     * @var \WeakMap<Message, true>
     */
    private \WeakMap $subscribedMessages;

    public function __construct()
    {
        $this->subscribedMessages = new \WeakMap();
    }

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
            // Check if payload is a Message
            if ($action->payload instanceof Message) {
                // Set up subscription BEFORE dispatching so it's ready when response arrives
                $this->setupMessageSubscription(
                    $action->payload,
                    $notificationChain
                );
            }

            // Now dispatch action (may execute handler and emit response)
            return $next($action);
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
        // Check if subscription already exists for this message
        if ($this->subscribedMessages->offsetExists($message)) {
            return;
        }

        // Mark as subscribed before setting up to prevent re-entry
        $this->subscribedMessages[$message] = true;

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

                // Remove from tracked messages since subscription is being cleaned up
                unset($this->subscribedMessages[$message]);

                // Auto cleanup - remove this subscription since we got our response
                $unsubscribe();
            }
        );
    }

    /**
     * Install correlation filtering optimization on NotificationChain
     *
     * Hooks Notification chain to detect outgoing Message requests and set up
     * subscriptions for correlated responses (used by abilities and other non-trigger paths)
     */
    private function installCorrelationFiltering(Notification $notificationChain): void
    {
        $notificationChain->link(function (Notify $notify, callable $next) use ($notificationChain) {
            // Check if event is a Message BEFORE processing
            if ($notify->event instanceof Message) {
                // Check if this message is a response to any request we're tracking
                // Responses should NOT get subscriptions set up for them
                $isResponse = false;
                foreach ($this->subscribedMessages as $trackedMessage => $_) {
                    if ($notify->event->repliesTo($trackedMessage)) {
                        $isResponse = true;
                        break;
                    }
                }

                // Only set up subscription for request messages, not responses
                if (!$isResponse) {
                    // Set up subscription for this message (if not already subscribed)
                    // This ensures the subscription is ready when the response is emitted
                    $this->setupMessageSubscription(
                        $notify->event,
                        $notificationChain
                    );
                }
            }

            // Now process the notification
            return $next($notify);
        });
    }
}
