<?php

declare(strict_types=1);

namespace Noem\State\Actor;

use Noem\State\StatefulActorInterface;
use Noem\State\StateInterface;

class StateActionProvider implements ActionProviderInterface
{

    public function getActionsForPayload(StateInterface $state, object $payload): iterable
    {
        if (!$state instanceof StatefulActorInterface) {
            yield from [];

            return;
        }

        yield [$state, 'action'];
    }
}