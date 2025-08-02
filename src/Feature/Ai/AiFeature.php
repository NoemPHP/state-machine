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
                $request = new RequestBuilder();

                if (isset($invocation->hash['max'])) {
                    $request->setMaxTokens((int)$invocation->hash['max']);
                }
                if (isset($invocation->hash['stop'])) {
                    $request->setStop(trim($invocation->hash['stop'], '"\''));
                }
                $prompt = "# Instruction\n\n";
                $prompt .= <<<'DOC'
You are a text completion assistant. Your task is to extend the provided text based on the user's instructions.

Rules:
1. Return only the extended text, with no wrapping formatting or explanations.
2. The first character of your response must be the first character of the code.
3. The last character of your response must be the last character of the code.
4. Instead of using triple backticks (```) or any other markdown, 
assume the content will be formatted properly by the surrounding application
5. Do not use any code block indicators, syntax highlighting markers, or any other formatting characters.
6. Present the text exactly as it would appear in a plain text editor, 
preserving all whitespace, indentation, and line breaks.
7. Maintain the original code structure and only make changes as specified by the user's instructions.
8. Ensure that the extended text is syntactically and semantically correct for the given text type.
9. Use consistent indentation and follow inferred style guidelines.

## Context


DOC;

                $prompt .= PHP_EOL;
                if (!$invocation->isBlock()) {
                    $prompt .= 'Complete the document provided.';
                    $prompt .= PHP_EOL;
                    $prompt .= PHP_EOL;
                    $prompt .= 'Input:';
                    $prompt .= PHP_EOL;
                    $prompt .= PHP_EOL;
                    $prompt .= PHP_EOL;
                    $prompt .= $invocation->getBuffer();

                    $request->setPrompt($prompt);
                    $generator = new Completion($request->build())();
                    while ($generator->valid()) {
                        $chunk = $generator->current();
                        yield $chunk;
                        $generator->next();
                    }
                    yield from $next($invocation);

                    return;
                }
                $prompt .= implode(iterator_to_array($invocation->blockContent(), false));
                $prompt .= <<<'DOC'

## Document (for completion)


DOC;
                //$prompt .= PHP_EOL;
                //$prompt .= PHP_EOL;
                //$prompt .= 'Input:';
                //$prompt .= PHP_EOL;
                //$prompt .= PHP_EOL;
                //$prompt .= PHP_EOL;
                $prompt .= $invocation->getBuffer();
                $request->setPrompt($prompt);
                yield '';
                yield from new Completion($request->build())();
                yield from $next($invocation);
            });

            $helpers->registerHelper('capture', function (Invocation $invocation, callable $next) {
                $key = $invocation->args[0];

                $schema = [
                    'type' => 'array',
                    'items' => [
                        'type' => 'string',
                    ],
                ];
                $prompt = '# Instructions';
                $prompt .= PHP_EOL;
                $prompt .= 'Extract data based on the provided input. Always respond in JSON format.';
                if ($invocation->isBlock()) {
                    $prompt .= PHP_EOL;
                    $prompt .= PHP_EOL . implode(iterator_to_array($invocation->blockContent($invocation), false));
                    $prompt .= PHP_EOL;
                }
                $prompt .= '# Input';
                $prompt .= PHP_EOL;
                $prompt .= $invocation->getBuffer();

                $request = new RequestBuilder()
                    ->setPrompt($prompt)
                    //                        ->setStop($invocation->hash['stop'] ?? '')
                    ->setResponseFormat(
                        new ResponseFormat(
                            'json_schema',
                            [
                                'name' => 'list',
                                'schema' => $schema,
                            ]
                        )
                    )
                    ->build();
                $generator = new Chat($request)();
                $result = implode(iterator_to_array($generator, false));
                $decoded = json_decode($result, true);
                $invocation->data[$key] = $decoded;
                return yield from $next($invocation);
            });
        });
    }
}
