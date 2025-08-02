<?php

declare(strict_types=1);

namespace Noem\State\Chains;

use Noem\State\Chains\Params;
use Noem\State\Connection;
use Noem\State\Middleware\Chain;
use Noem\State\Region;

/**
 * Responsible for returning a list of Regions that are connected to the current region
 *
 * @template-extends Chain<Connection,list<Region>>
 */
class ConnectedRegions extends Chain
{
    /**
     * @var list<Connection>
     */
    private array $connections = [];

    public function __construct()
    {
        parent::__construct(fn() => []);
        $this->link(
            function (Params\Connection $context, callable $next, callable $first): array {
                $list = $next($context);
                foreach ($this->connections as $connection) {
                    /**
                     * Identify the corresponding _other_ region depending on context
                     */
                    $connectedRegion = match ($context->region) {
                        $connection->local => $context->searchMode
                            ? $connection->remote
                            : null,
                        $connection->remote => $context->searchMode
                            ? null
                            : $connection->local,
                        default => null,
                    };
                    if (!$connectedRegion) {
                        continue;
                    }
                    if (
                        $context->hasFlag(Connection::DYNAMIC)
                        && $connection->hasFlag(Connection::DYNAMIC)
                        && !$connection->isActive()
                    ) {
                        continue;
                    }

                    if (
                        $context->hasFlag(Connection::RECEIVE_META)
                        && !$connection->hasFlag(Connection::RECEIVE_META)
                    ) {
                        continue;
                    }
                    /**
                     * TODO implement other flags
                     */
                    $list[] = $connectedRegion;
                }

                return $list;
            }
        );
    }

    public function addConnection(Connection $type): self
    {
        $this->connections[] = $type;

        return $this;
    }
}
