<?php

declare(strict_types=1);

namespace Noem\State\Feature\Logging;

use Noem\State\Feature\ExtendedState\ContextChains\BoundAccess;
use Noem\State\Feature\ExtendedState\ContextChains\Params\BoundAccessParams;
use Noem\State\Feature\Feature;
use Noem\State\Middleware\ChainMail;
use Override;

/**
 * Structured logging via Chain/Params infrastructure
 *
 * LoggingFeature provides:
 * - LoggingChain middleware for intercepting and routing log entries
 * - LogParams encapsulation of log data
 * - $this->log() context helper for ergonomic logging in state callbacks
 *
 * Usage in state callbacks:
 * ```php
 * ->onAction('processing', function(object $t) {
 *     $this->log('info', 'Starting processing', ['itemId' => $t->id]);
 * })
 * ```
 *
 * To handle logs, link middleware to LoggingChain:
 * ```php
 * $loggingChain->link(function(LogParams $params, callable $next) {
 *     error_log("[{$params->level}] {$params->message}");
 *     return $next($params);
 * });
 * ```
 */
class LoggingFeature implements Feature
{
    #[Override]
    public function __invoke(ChainMail $chainMail): void
    {
        // Register LoggingChain in middleware stack
        $chainMail->supply(fn(): LoggingChain => new LoggingChain());

        // Bind $this->log() method if BoundAccess exists (ExtendedState loaded)
        $chainMail->use(function (?BoundAccess $boundAccess, ?LoggingChain $loggingChain) {
            if ($boundAccess === null || $loggingChain === null) {
                return;
            }

            $boundAccess->link(function (BoundAccessParams $params, callable $next) use ($loggingChain) {
                // Only handle 'log' method calls
                if ($params->type !== BoundAccessParams::TYPE_METHOD || $params->name !== 'log') {
                    return $next($params);
                }

                // Extract log parameters from payload
                $level = $params->payload[0] ?? 'info';
                $message = $params->payload[1] ?? '';
                $context = $params->payload[2] ?? null;

                // Create LogParams and dispatch through LoggingChain
                $logParams = new LogParams(
                    region: $params->region,
                    level: $level,
                    message: $message,
                    context: $context
                );

                $loggingChain->call($logParams);

                return null;
            }, prepend: true);
        });
    }
}
