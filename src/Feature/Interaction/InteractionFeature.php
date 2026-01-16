<?php

declare(strict_types=1);

namespace Noem\State\Feature\Interaction;

use Noem\State\Chains\Notification;
use Noem\State\Chains\Params\Notify;
use Noem\State\Feature\ExtendedState\ContextChains\BoundAccess;
use Noem\State\Feature\ExtendedState\ContextChains\Params\BoundAccessParams;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\Feature;
use Noem\State\Feature\RequiresFeature;
use Noem\State\Middleware\ChainException;
use Noem\State\Middleware\ChainMail;
use Noem\State\Region;

/**
 * Agentic interaction patterns feature
 *
 * Enables state machines to request information/decisions from external agents
 * through standardized interaction patterns (Confirm, Select, Choice, Prompt).
 *
 * Requires:
 * - ExtendedState (for BoundAccess)
 * - SubscriptionFeature (for event emission)
 * - MessageFeature (for correlation)
 */
#[RequiresFeature(ExtendedState::class)]
class InteractionFeature implements Feature
{
    #[Override]
    public function __invoke(ChainMail $chainMail): void
    {
        // Verify ExtendedState is loaded (when not using FeatureRegistry)
        try {
            $chainMail->get(BoundAccess::class);
        } catch (ChainException $e) {
            throw new \RuntimeException(
                'InteractionFeature requires ExtendedState to be loaded first',
                0,
                $e
            );
        }

        $chainMail->use($this->bindInteractMethod(...));
    }

    /**
     * Bind $this->interact() method to BoundAccess
     *
     * Supports two signatures:
     * - $this->interact(InteractionRequest) - Direct request
     * - $this->interact(string $id, array $overrides = []) - Registry lookup
     */
    private function bindInteractMethod(
        BoundAccess $boundAccess,
        ?InteractionRegistry $registry = null
    ): void {
        $boundAccess->link(function (BoundAccessParams $params, callable $next) use ($registry) {
            if ($params->type !== BoundAccessParams::TYPE_METHOD || $params->name !== 'interact') {
                return $next($params);
            }

            $firstParam = $params->payload[0] ?? null;

            // Signature 1: $this->interact(InteractionRequest)
            if ($firstParam instanceof InteractionRequest) {
                return $this->handleInteraction($params->region, $firstParam);
            }

            // Signature 2: $this->interact(string $id, array $overrides = [])
            if (is_string($firstParam)) {
                $request = $this->createRequestFromRegistry(
                    $firstParam,
                    $params->payload[1] ?? [],
                    $registry
                );
                return $this->handleInteraction($params->region, $request);
            }

            throw new \InvalidArgumentException(
                'interact() requires InteractionRequest instance or string $id'
            );
        }, prepend: true);
    }

    /**
     * Create InteractionRequest from registry definition
     *
     * @param string $id Definition ID
     * @param array $overrides Runtime overrides (question, context, timeoutMs, etc.)
     * @param InteractionRegistry|null $registry
     * @return InteractionRequest
     */
    private function createRequestFromRegistry(
        string $id,
        array $overrides,
        ?InteractionRegistry $registry
    ): InteractionRequest {
        if ($registry === null) {
            throw new \RuntimeException(
                'InteractionRegistry not available. Load InteractionRegistryFeature to use interact($id).'
            );
        }

        $definition = $registry->get($id);
        if ($definition === null) {
            throw new \InvalidArgumentException(
                "Interaction definition '$id' not found in registry"
            );
        }

        // Merge definition with runtime overrides
        $question = $overrides['question'] ?? $definition->question;
        $context = $overrides['context'] ?? $definition->metadata['context'] ?? null;
        $timeoutMs = $overrides['timeoutMs'] ?? $definition->metadata['timeoutMs'] ?? null;

        // Create appropriate request type based on definition (with interaction ID)
        return match ($definition->type) {
            'confirm' => new ConfirmRequest(
                question: $question,
                defaultValue: $overrides['defaultValue']
                    ?? $definition->metadata['defaultValue']
                    ?? false,
                context: $context,
                timeoutMs: $timeoutMs,
                interactionId: $id
            ),
            'select' => new SelectRequest(
                question: $question,
                options: $this->buildSelectOptions(
                    $overrides['options'] ?? $definition->options ?? []
                ),
                defaultKey: $overrides['defaultKey']
                    ?? $definition->metadata['defaultKey']
                    ?? null,
                context: $context,
                timeoutMs: $timeoutMs,
                interactionId: $id
            ),
            'choice' => new ChoiceRequest(
                question: $question,
                options: $this->buildChoiceOptions(
                    $overrides['options'] ?? $definition->options ?? []
                ),
                defaultKeys: $overrides['defaultKeys']
                    ?? $definition->metadata['defaultKeys']
                    ?? [],
                minSelections: $overrides['minSelections']
                    ?? $definition->constraints['minSelections']
                    ?? null,
                maxSelections: $overrides['maxSelections']
                    ?? $definition->constraints['maxSelections']
                    ?? null,
                context: $context,
                timeoutMs: $timeoutMs,
                interactionId: $id
            ),
            'prompt' => new PromptRequest(
                question: $question,
                placeholder: $overrides['placeholder']
                    ?? $definition->metadata['placeholder']
                    ?? null,
                defaultValue: $overrides['defaultValue']
                    ?? $definition->metadata['defaultValue']
                    ?? null,
                validation: $overrides['validation']
                    ?? $definition->constraints['validation']
                    ?? null,
                multiline: $overrides['multiline']
                    ?? $definition->metadata['multiline']
                    ?? false,
                context: $context,
                timeoutMs: $timeoutMs,
                interactionId: $id
            ),
            default => throw new \InvalidArgumentException(
                "Unknown interaction type: $definition->type"
            )
        };
    }

    /**
     * Build SelectOption array from definition options
     *
     * @param array $options
     * @return array<string, SelectOption>
     */
    private function buildSelectOptions(array $options): array
    {
        $result = [];
        foreach ($options as $key => $option) {
            if ($option instanceof SelectOption) {
                $result[$key] = $option;
            } elseif (is_array($option)) {
                $result[$key] = new SelectOption(
                    $option['label'] ?? (string)$key,
                    $option['description'] ?? null
                );
            } else {
                $result[$key] = new SelectOption((string)$option);
            }
        }
        return $result;
    }

    /**
     * Build ChoiceOption array from definition options
     *
     * @param array $options
     * @return array<string, ChoiceOption>
     */
    private function buildChoiceOptions(array $options): array
    {
        $result = [];
        foreach ($options as $key => $option) {
            if ($option instanceof ChoiceOption) {
                $result[$key] = $option;
            } elseif (is_array($option)) {
                $result[$key] = new ChoiceOption(
                    $option['label'] ?? (string)$key,
                    $option['description'] ?? null,
                    $option['recommended'] ?? false
                );
            } else {
                $result[$key] = new ChoiceOption((string)$option);
            }
        }
        return $result;
    }

    /**
     * Handle interaction request-response cycle
     *
     * @return \Generator<mixed, null, mixed, mixed>
     */
    private function handleInteraction(Region $region, InteractionRequest $request): \Generator
    {
        $response = null;

        // Register response handler
        $request->then(function (InteractionResponse $r) use (&$response) {
            $response = $r;
        });

        // Emit interaction request to subscribers (framework adapters)
        $listeners = $region->notificationChain->call(new Notify($region, $request));
        foreach ($listeners as $listener) {
            $listener($request, $region);
        }

        // Yield until response received
        while ($response === null) {
            yield;
        }

        // Return response value or throw if cancelled
        if ($response->cancelled) {
            throw new InteractionCancelledException(
                "Interaction cancelled or timed out: $request->question"
            );
        }

        return $response->value;
    }
}
