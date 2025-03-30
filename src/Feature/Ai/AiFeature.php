<?php

declare(strict_types=1);

namespace Noem\State\Feature\Ai;

use Noem\State\Feature\Ai\Chains\SystemPrompt;
use Noem\State\Feature\Feature;
use Noem\State\Feature\Template\Compiler\Invocation;
use Noem\State\Feature\Template\Helpers;
use Noem\State\Middleware\ChainMail;

class AiFeature implements Feature
{
    public function __invoke(ChainMail $chainMail): void
    {
        $chainMail->supply(fn(): SystemPrompt => new SystemPrompt());
        $chainMail->use(function (Helpers $helpers): void {
            $helpers->registerHelper('complete', function (Invocation $invocation, callable $next) {
                $private = clone $invocation;
                $upcoming = $next($private);

                if (!$invocation->isBlock) {
                    yield from new Completion($invocation->buffer)();
                    yield from $upcoming;

                    return;
                }
                $prompt = implode(iterator_to_array($upcoming, false));
                yield from new Completion($prompt . PHP_EOL . $invocation->buffer)();
            });
        });
    }
}
