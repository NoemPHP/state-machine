<?php

declare(strict_types=1);

namespace Noem\State\Feature\Template;

use Noem\State\Feature\Async\Call;
use Noem\State\Feature\ExtendedState\ContextChains\BoundAccess;
use Noem\State\Feature\ExtendedState\ContextChains\Params\BoundAccessParams;
use Noem\State\Feature\Feature;
use Noem\State\Feature\Template\Compiler\TemplateFactory;
use Noem\State\Middleware\ChainMail;

class TemplateFeature implements Feature
{

    public function __invoke(ChainMail $chainMail): void
    {
        $chainMail->supply(
            fn(): Helpers => new Helpers(),
            fn(Helpers $h): TemplateFactory => new TemplateFactory($h)
        );
        $chainMail->use(function (
            BoundAccess $boundAccess,
            TemplateFactory $templateFactory,
        ) {
            $boundAccess->link(function (BoundAccessParams $params, callable $next) use ($templateFactory) {
                if ($params->type !== BoundAccessParams::TYPE_METHOD) {
                    return $next($params);
                }
                switch ($params->name) {
                    case 'template':
                        $args = $params->payload;
                        $key = array_shift($args);
                        $template = $templateFactory->create($key);

                        return (function () use ($template, $args) {
                            $generator = $template($args);
                            $result = '';
                            while ($generator->valid()) {
                                $chunk = $generator->current();
                                $result .= $chunk;
                                $generator->next();
                                yield $chunk;
                            }

                            return $result;
                        })();

                    default:
                        return $next($params);
                }
            });
        });
    }
}
