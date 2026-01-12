<?php

declare(strict_types=1);

namespace CliAgent;

use Noem\State\Feature\Interaction\InteractionRequest;
use Noem\State\Feature\Interaction\PromptRequest;
use Noem\State\Feature\Interaction\PromptResponse;
use Noem\State\Feature\Interaction\ConfirmRequest;
use Noem\State\Feature\Interaction\ConfirmResponse;
use Noem\State\Feature\Interaction\SelectRequest;
use Noem\State\Feature\Interaction\SelectResponse;
use Noem\State\Feature\Interaction\ChoiceRequest;
use Noem\State\Feature\Interaction\ChoiceResponse;
use Noem\State\Region;

/**
 * CLI-based interaction adapter
 *
 * Subscribes to InteractionRequest events from a summoned machine
 * and handles them via STDIN/STDOUT, acting as a framework adapter
 * for the Interaction feature system.
 */
class InteractionAdapter
{
    private Region $targetMachine;
    private bool $verbose;
    private array $prefilledResponses = [];

    public function __construct(Region $targetMachine, bool $verbose = false)
    {
        $this->targetMachine = $targetMachine;
        $this->verbose = $verbose;
    }

    /**
     * Pre-fill a response for a specific interaction ID
     *
     * When the machine requests this interaction, the adapter will
     * immediately respond with the pre-filled value instead of prompting the user.
     *
     * @param string $interactionId The interaction ID to pre-fill
     * @param mixed $value The value to respond with
     */
    public function prefill(string $interactionId, mixed $value): void
    {
        $this->prefilledResponses[$interactionId] = $value;
    }

    /**
     * Attach adapter to target machine
     *
     * Subscribes to notification events to handle InteractionRequest events
     */
    public function attach(): void
    {
        // Subscribe a listener that handles InteractionRequest events
        // Listener signature: function(object $event, ?Region $region)
        $this->targetMachine->notificationChain->subscribe(
            function (object $event, ?Region $region): void {
                // Only handle InteractionRequest events
                if (!$event instanceof InteractionRequest) {
                    return;
                }

                if ($this->verbose) {
                    echo "\n[InteractionAdapter] Received " . get_class($event) . " interaction\n";
                }

                $request = $event;
                $response = $this->handleInteraction($request);

                // Deliver response back to the request (triggers registered callbacks)
                $request->deliverResponse($response);
            }
        );

        if ($this->verbose) {
            echo "[InteractionAdapter] Attached to machine\n";
        }
    }

    /**
     * Handle interaction based on type
     */
    private function handleInteraction(InteractionRequest $request): object
    {
        // Check if this interaction has a prefilled response
        if ($request->interactionId !== null && isset($this->prefilledResponses[$request->interactionId])) {
            if ($this->verbose) {
                echo "[InteractionAdapter] Using prefilled response for '{$request->interactionId}'\n";
            }

            $prefilledValue = $this->prefilledResponses[$request->interactionId];

            // Create appropriate response type based on request type
            return match (true) {
                $request instanceof PromptRequest => $request->createResponse(PromptResponse::class, [
                    'input' => $prefilledValue,
                    'cancelled' => false,
                ]),
                $request instanceof ConfirmRequest => $request->createResponse(ConfirmResponse::class, [
                    'confirmed' => (bool)$prefilledValue,
                    'cancelled' => false,
                ]),
                $request instanceof SelectRequest => $request->createResponse(SelectResponse::class, [
                    'selectedKey' => $prefilledValue,
                    'cancelled' => false,
                ]),
                $request instanceof ChoiceRequest => $request->createResponse(ChoiceResponse::class, [
                    'selectedKeys' => (array)$prefilledValue,
                    'cancelled' => false,
                ]),
                default => throw new \RuntimeException(
                    'Unknown interaction type: ' . get_class($request)
                )
            };
        }

        // No prefilled response - prompt the user
        return match (true) {
            $request instanceof PromptRequest => $this->handlePrompt($request),
            $request instanceof ConfirmRequest => $this->handleConfirm($request),
            $request instanceof SelectRequest => $this->handleSelect($request),
            $request instanceof ChoiceRequest => $this->handleChoice($request),
            default => throw new \RuntimeException(
                'Unknown interaction type: ' . get_class($request)
            )
        };
    }

    /**
     * Handle prompt interaction via STDIN
     */
    private function handlePrompt(PromptRequest $request): PromptResponse
    {
        // Display question
        echo $request->question;
        if ($request->context) {
            echo " ({$request->context})";
        }
        echo "\n";

        // Show placeholder if provided
        if ($request->placeholder) {
            echo "[{$request->placeholder}]\n";
        }

        // Show default value if provided
        if ($request->defaultValue) {
            echo "[Default: {$request->defaultValue}]\n";
        }

        echo "> ";
        $input = fgets(STDIN);

        // Handle EOF (false return from fgets)
        if ($input === false) {
            return $request->createResponse(PromptResponse::class, [
                'input' => null,
                'cancelled' => true,
            ]);
        }

        $input = trim($input);

        // Check for cancellation
        if (strtolower($input) === 'exit' || strtolower($input) === 'cancel') {
            return $request->createResponse(PromptResponse::class, [
                'input' => null,
                'cancelled' => true,
            ]);
        }

        // Use default if empty
        if ($input === '' && $request->defaultValue) {
            $input = $request->defaultValue;
        }

        // Validate if pattern provided
        if ($request->validation && !preg_match($request->validation, $input)) {
            echo "Invalid input (does not match pattern: {$request->validation})\n";
            return $this->handlePrompt($request); // Retry
        }

        return $request->createResponse(PromptResponse::class, [
            'input' => $input,
            'cancelled' => false,
        ]);
    }

    /**
     * Handle confirm interaction via STDIN
     */
    private function handleConfirm(ConfirmRequest $request): ConfirmResponse
    {
        // Display question
        echo $request->question;

        $defaultLabel = $request->defaultValue ? '[Y/n]' : '[y/N]';
        echo " {$defaultLabel}: ";

        $rawInput = fgets(STDIN);
        if ($rawInput === false) {
            return $request->createResponse(ConfirmResponse::class, [
                'confirmed' => false,
                'cancelled' => true,
            ]);
        }

        $input = strtolower(trim($rawInput));

        // Check for cancellation
        if ($input === 'exit' || $input === 'cancel') {
            return $request->createResponse(ConfirmResponse::class, [
                'confirmed' => false,
                'cancelled' => true,
            ]);
        }

        // Determine confirmation
        if ($input === '') {
            $confirmed = $request->defaultValue;
        } elseif ($input === 'y' || $input === 'yes') {
            $confirmed = true;
        } elseif ($input === 'n' || $input === 'no') {
            $confirmed = false;
        } else {
            echo "Please answer 'y' or 'n'\n";
            return $this->handleConfirm($request); // Retry
        }

        return $request->createResponse(ConfirmResponse::class, [
            'confirmed' => $confirmed,
            'cancelled' => false,
        ]);
    }

    /**
     * Handle select interaction via STDIN
     */
    private function handleSelect(SelectRequest $request): SelectResponse
    {
        echo $request->question . "\n\n";

        // Display options
        $keys = array_keys($request->options);
        foreach ($keys as $index => $key) {
            $option = $request->options[$key];
            $marker = ($key === $request->defaultKey) ? '*' : ' ';
            echo "  {$marker} [{$index}] {$option->label}";
            if ($option->description) {
                echo " - {$option->description}";
            }
            echo "\n";
        }

        echo "\nSelect option (0-" . (count($keys) - 1) . "): ";
        $rawInput = fgets(STDIN);
        if ($rawInput === false) {
            return $request->createResponse(SelectResponse::class, [
                'selectedKey' => null,
                'cancelled' => true,
            ]);
        }

        $input = trim($rawInput);

        // Check for cancellation
        if (strtolower($input) === 'exit' || strtolower($input) === 'cancel') {
            return $request->createResponse(SelectResponse::class, [
                'selectedKey' => null,
                'cancelled' => true,
            ]);
        }

        // Use default if empty
        if ($input === '' && $request->defaultKey !== null) {
            $selectedKey = $request->defaultKey;
        } else {
            $selectedIndex = (int)$input;
            if (!isset($keys[$selectedIndex])) {
                echo "Invalid selection\n";
                return $this->handleSelect($request); // Retry
            }
            $selectedKey = $keys[$selectedIndex];
        }

        return $request->createResponse(SelectResponse::class, [
            'selectedKey' => $selectedKey,
            'cancelled' => false,
        ]);
    }

    /**
     * Handle choice (multi-select) interaction via STDIN
     */
    private function handleChoice(ChoiceRequest $request): ChoiceResponse
    {
        echo $request->question . "\n";
        if ($request->minSelections || $request->maxSelections) {
            echo "(Select ";
            if ($request->minSelections) {
                echo "at least {$request->minSelections}";
            }
            if ($request->minSelections && $request->maxSelections) {
                echo ", ";
            }
            if ($request->maxSelections) {
                echo "at most {$request->maxSelections}";
            }
            echo ")\n";
        }
        echo "\n";

        // Display options
        $keys = array_keys($request->options);
        foreach ($keys as $index => $key) {
            $option = $request->options[$key];
            $marker = in_array($key, $request->defaultKeys) ? '*' : ' ';
            $recommend = $option->recommended ? ' (recommended)' : '';
            echo "  {$marker} [{$index}] {$option->label}{$recommend}";
            if ($option->description) {
                echo " - {$option->description}";
            }
            echo "\n";
        }

        echo "\nSelect options (comma-separated, e.g., 0,2,3): ";
        $rawInput = fgets(STDIN);
        if ($rawInput === false) {
            return $request->createResponse(ChoiceResponse::class, [
                'selectedKeys' => [],
                'cancelled' => true,
            ]);
        }

        $input = trim($rawInput);

        // Check for cancellation
        if (strtolower($input) === 'exit' || strtolower($input) === 'cancel') {
            return $request->createResponse(ChoiceResponse::class, [
                'selectedKeys' => [],
                'cancelled' => true,
            ]);
        }

        // Use defaults if empty
        if ($input === '' && !empty($request->defaultKeys)) {
            $selectedKeys = $request->defaultKeys;
        } else {
            $indices = array_map('intval', explode(',', $input));
            $selectedKeys = [];
            foreach ($indices as $index) {
                if (isset($keys[$index])) {
                    $selectedKeys[] = $keys[$index];
                }
            }
        }

        // Validate constraints
        $count = count($selectedKeys);
        if ($request->minSelections && $count < $request->minSelections) {
            echo "Please select at least {$request->minSelections} option(s)\n";
            return $this->handleChoice($request); // Retry
        }
        if ($request->maxSelections && $count > $request->maxSelections) {
            echo "Please select at most {$request->maxSelections} option(s)\n";
            return $this->handleChoice($request); // Retry
        }

        return $request->createResponse(ChoiceResponse::class, [
            'selectedKeys' => $selectedKeys,
            'cancelled' => false,
        ]);
    }
}
