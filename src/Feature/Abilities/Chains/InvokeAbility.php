<?php

declare(strict_types=1);

namespace Noem\State\Feature\Abilities\Chains;

use JsonSchema\Validator;
use Noem\State\Chains\Notification;
use Noem\State\Chains\Params\Notify;
use Noem\State\Feature\Abilities\AbilityMessage;
use Noem\State\Feature\Abilities\AbilityNotFoundException;
use Noem\State\Feature\Abilities\AbilityRegistry;
use Noem\State\Feature\Abilities\Chains\Params\InvokeAbility as InvokeAbilityParams;
use Noem\State\Feature\Abilities\Chains\Params\ExecuteAbilityHandler as ExecuteParams;
use Noem\State\Feature\Abilities\SchemaValidationException;
use Noem\State\Middleware\Chain;

/**
 * Stateless chain for ability invocation with middleware support
 *
 * Provides type-safe chain for ability invocations following DispatchAction/Notification pattern.
 * Handles registry lookup, schema validation, message creation, and dispatch.
 *
 * @template-extends Chain<InvokeAbilityParams, AbilityMessage>
 */
class InvokeAbility extends Chain
{
    public function __construct(
        private readonly AbilityRegistry $registry,
        private readonly Notification $notificationChain,
        private readonly ExecuteAbilityHandler $executeChain,
        private readonly ProcessAbilityResult $processResultChain,
    ) {
        parent::__construct($this->invoke(...));
    }

    /**
     * Invoke ability with validation and execution
     *
     * Creates AbilityMessage, emits via Notification (sets up MessageFeature subscription),
     * executes handler via ExecuteAbilityHandler chain (hookable by AsyncFeature),
     * and emits response via Notification.
     *
     * @param InvokeAbilityParams $params Invocation parameters
     * @return AbilityMessage Created message for correlation
     * @throws AbilityNotFoundException If ability not found or hidden by predicate
     * @throws SchemaValidationException If parameters fail validation
     */
    private function invoke(InvokeAbilityParams $params): AbilityMessage
    {
        // Lookup ability definition
        $definition = $this->registry->get($params->abilityName);

        if ($definition === null) {
            throw new AbilityNotFoundException($params->abilityName);
        }

        // Evaluate predicate if present
        if ($definition->predicate !== null) {
            $isAvailable = $this->evaluatePredicate($definition->predicate, $params->region);

            if (!$isAvailable) {
                throw new AbilityNotFoundException($params->abilityName);
            }
        }

        // Validate parameters against schema
        $this->validateParameters($params->parameters, $definition->parameterSchema, $params->abilityName);

        // Create ability message
        $message = AbilityMessage::create(
            abilityName: $params->abilityName,
            parameters: $params->parameters,
            definition: $definition,
        );

        // Emit request message via Notification (sets up MessageFeature subscription)
        $listeners = $this->notificationChain->call(new Notify($params->region, $message));
        foreach ($listeners as $listener) {
            $listener($message, $params->region);
        }

        // Execute handler via ExecuteAbilityHandler chain (AsyncFeature can hook this)
        $executeParams = new ExecuteParams(
            handler: $definition->handler,
            parameters: $params->parameters,
            region: $params->region,
        );
        $handlerResult = $this->executeChain->call($executeParams);

        // Process result through ProcessAbilityResult chain
        // AsyncFeature can hook this to handle Task objects asynchronously
        $processParams = new Params\ProcessAbilityResult(
            message: $message,
            handlerResult: $handlerResult,
            definition: $definition,
            region: $params->region,
        );
        $this->processResultChain->call($processParams);

        // Return message immediately for chaining (non-blocking if async)
        return $message;
    }

    /**
     * Evaluate predicate for conditional exposure
     *
     * @param callable $predicate Predicate callable receiving Region
     * @param \Noem\State\Region $region Region context for evaluation
     * @return bool True if ability available, false otherwise
     */
    private function evaluatePredicate(callable $predicate, \Noem\State\Region $region): bool
    {
        try {
            return (bool) $predicate($region);
        } catch (\Throwable $e) {
            // Treat exceptions as hidden
            return false;
        }
    }

    /**
     * Validate parameters against JSON schema
     *
     * @param mixed $parameters Parameters to validate
     * @param array $schema JSON Schema definition
     * @param string $abilityName Ability name for error context
     * @throws SchemaValidationException If validation fails
     */
    private function validateParameters(mixed $parameters, array $schema, string $abilityName): void
    {
        // Skip validation for empty schema
        if (empty($schema)) {
            return;
        }

        // Convert null or empty array to empty object for schema validation
        if ($parameters === null || (is_array($parameters) && empty($parameters))) {
            $parameters = new \stdClass();
        }

        // Convert parameters to object for validation
        $data = json_decode(json_encode($parameters));

        // Convert schema to object
        $schemaObject = json_decode(json_encode($schema));

        // Validate using justinrainbow/json-schema
        $validator = new Validator();
        $validator->validate($data, $schemaObject);

        if (!$validator->isValid()) {
            // Convert validator errors to structured format
            $errors = array_map(
                fn($error) => [
                    'path' => $error['property'] ?? '',
                    'message' => $error['message'] ?? '',
                ],
                $validator->getErrors()
            );

            throw new SchemaValidationException(
                sprintf('Parameter validation failed for ability "%s"', $abilityName),
                $errors
            );
        }
    }
}
