<?php

declare(strict_types=1);

namespace Noem\State\Feature\Loader\Helper;

use Noem\State\Feature\Loader\Container;

readonly class ContainerGetHelper
{
    private Container $container;

    public function __construct(?iterable $container = [])
    {
        $this->container = new Container($container);
    }

    public function __invoke(string $content): mixed
    {
        return $this->container->offsetGet($content);
    }
}
