<?php

declare(strict_types=1);

namespace Noem\State\Feature\Loader\Helper;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

readonly class ContainerGetHelper
{
    public function __construct(private ContainerInterface $container)
    {
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __invoke(string $content): mixed
    {
        return $this->container?->get($content);
    }
}
