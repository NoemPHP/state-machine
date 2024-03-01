<?php

declare(strict_types=1);

namespace Noem\State\Helper;

class PhpEvalHelper
{
    public function __invoke(string $content): mixed
    {
        return eval($content);
    }
}
