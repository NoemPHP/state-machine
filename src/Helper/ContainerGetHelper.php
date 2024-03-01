<?php

declare(strict_types=1);

namespace Noem\State\Helper;

use Psr\Container\ContainerInterface;

class ContainerGetHelper
{

    public function __construct(private ContainerInterface $container)
    {
    }

    public function __invoke(string $content): mixed
    {
        if ($this->container) {
            return $this->container->get($content);
        }

        return null;
    }
}
