<?php

declare(strict_types=1);

namespace Noem\State\Feature\Abilities\Chains;

use Noem\State\Chains\Notification;
use Noem\State\Feature\Abilities\AbilityMessage;
use Noem\State\Middleware\Chain;

/**
 * Chain for processing ability handler results
 *
 * Enables features like AsyncFeature to intercept results and handle them
 * appropriately (e.g., register completion callbacks for async Tasks).
 *
 * Provider (final handler) creates and dispatches the response message.
 *
 * @template-extends Chain<Params\ProcessAbilityResult, void>
 */
class ProcessAbilityResult extends Chain
{
    public function __construct(Notification $notificationChain)
    {
        parent::__construct(function (Params\ProcessAbilityResult $params) use ($notificationChain) {
            // Create correlated response message
            $response = $params->message->createResponse(
                AbilityMessage::class,
                [
                    'abilityName' => $params->message->abilityName,
                    'parameters' => $params->handlerResult,
                    'definition' => $params->definition,
                ]
            );

            // Emit response via Notification (triggers MessageFeature subscription)
            $listeners = $notificationChain->call(new \Noem\State\Chains\Params\Notify($params->region, $response));
            foreach ($listeners as $listener) {
                $listener($response, $params->region);
            }
        });
    }
    protected function getInputType(): string
    {
        return Params\ProcessAbilityResult::class;
    }
}
