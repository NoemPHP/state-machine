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
                    $request = new RequestBuilder()
                        ->setPrompt($invocation->buffer)
                        ->setStop($invocation->hash['stop'] ?? '')
                        ->build();
                    $generator = new Completion($request)();
                    while ($generator->valid()) {
                        $chunk = $generator->current();
                        yield $chunk;
                        $generator->next();
                    }
                    yield from $upcoming;

                    return;
                }
                $prompt = implode(iterator_to_array($upcoming, false));
                $request = new RequestBuilder()
                    ->setPrompt($prompt . PHP_EOL . $invocation->buffer)
                    ->setStop($invocation->hash['stop'] ?? '')
                    ->build();
                yield from new Completion($request)();
            });

            $helpers->registerHelper('capture', function (Invocation $invocation, callable $next) {
                $key = $invocation->args[0];
                $upcoming = $next($invocation);
                $schema = [
                    'type' => 'array',
                    'items' => [
                        'type' => 'string'
                    ]
                ];
                $prompt = $invocation->buffer;
                if ($invocation->isBlock) {
                    $prompt .= PHP_EOL . implode(iterator_to_array($upcoming, false));
                }
                $prompt .= PHP_EOL . "Return as JSON";


                $request = new RequestBuilder()
                    ->setPrompt($prompt)
//                        ->setStop($invocation->hash['stop'] ?? '')
                    ->setResponseFormat(new ResponseFormat(
                        'json_schema',
                        [
                            'name' => 'list',
                            'schema' => $schema,
                        ]))
                    ->build();
                $generator = new Chat($request)();
                $result = implode(iterator_to_array($generator, false));
                $decoded = json_decode($result, true);
                $invocation->data[$key] = $decoded;
                if (!$invocation->isBlock) {
                    yield from $upcoming;
                }
                return yield '';
            });
        });
    }
}
