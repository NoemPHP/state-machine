<?php

declare(strict_types=1);

namespace Noem\State\Feature\Template\Compiler;

use Noem\State\Feature\Template\Helpers;
use Noem\State\Middleware\Chain;

class TemplateFactory
{
    /**
     * @var array<Chain>
     */
    private array $levels;

    private int $currentLevel = 0;// used to track the current level of nesting

    private const LAST_OPEN = ' LAST ';

    public function __construct(private readonly Helpers $helpers)
    {
    }

    private function getLevel(?int $level = null): Chain
    {
        $level = $level ?? $this->currentLevel;
        if (!isset($this->levels[$level])) {
            $this->levels[$level] = new Chain(function () {
                yield '';
            });
        }

        return $this->levels[$level];
    }

    public function render(string $template): callable
    {
        $this->levels = [];
        $this->currentLevel = 0;
        $reference = new \stdClass();
        $reference->buffer = '';
        $reference->open = [];
        $tokenizer = new Tokenizer($template);
        foreach ($tokenizer->process() as $node) {
            assert($node instanceof Node);
            switch ($node->type) {
                case NodeType::TEXT:
                    //$this->getLevel()->link($this->appendBuffer());
                    $this->getLevel()->link($this->generateText($node, $reference->open));
                    break;
                case NodeType::VARIABLE_ESCAPE:
                    //$this->getLevel()->link($this->appendBuffer());
                    $this->getLevel()->link($this->generateVariable($node, $reference->open, true));
                    break;
                case NodeType::VARIABLE_UNESCAPE:
                    //$this->getLevel()->link($this->appendBuffer());
                    $this->getLevel()->link($this->generateVariable($node, $reference->open));
                    break;
                case NodeType::SECTION_OPEN:
                    //$this->getLevel()->link($this->appendBuffer());
                    $this->currentLevel++;
                    $this->getLevel()->link($this->generateOpen($node, $reference->open));
                    break;
                case NodeType::SECTION_CLOSE:
                    //$this->getLevel()->link($this->appendBuffer());
                    $this->currentLevel--;
                    $this->getLevel()->link($this->generateClose($node, $reference->open));
                    break;
            }
        }
        if (count($reference->open)) {
            throw new \RuntimeException("template still open");
        }

        return function (array|\ArrayAccess|null $context = []) {
            $chain = new Chain(function () {
                yield '';
            });
            foreach ($this->levels as $level) {
                $chain->link(function (Invocation $data, callable $next) use ($level) {
                    yield from $level->call($data);
                    yield from $next($data);
                });
            }
            $invocation = new Invocation($context, [], []);
            yield from $chain->call($invocation);
        };
    }

    private function appendBuffer()
    {
        return function (Invocation $data, callable $next) {
            $upcoming = $next($data);
            assert($upcoming instanceof \Generator);
            while ($upcoming->valid()) {
                $chunk = $upcoming->current();
                $data->append($chunk);
                yield $chunk;
                $upcoming->next();
            }
        };
    }

    /**
     * @param string $template
     *
     * @return callable
     */
    public function create(string $template): \Closure
    {
        return $this->render($template);
    }

    protected function generateOpen(Node $node, array &$open): \Closure
    {
        $nodeValue = trim($node->value);

        //push in the node, we are going to need this to close
        $open[] = $node;

        [$name, $args, $hash] = $this->parseArguments($nodeValue);

        return function (Invocation $data, callable $next) use ($name, $args, $hash) {
            if (isset($this->helpers[$name])) {
                $invocation = $data->withArgs($args)->withHash($hash)->withBlockFlag(true);

                $iterator = $this->helpers[$name]($invocation, $next);
                while ($iterator->valid()) {
                    $chunk = $iterator->current();
                    $data->append($chunk);
                    yield $chunk;
                    $iterator->next();
                }
            }

            return yield from $next($data);
        };
    }

    protected function generateClose(Node $node, array &$open): \Closure
    {
        $nodeValue = trim($node->value);

        if ($this->findSection($open, $nodeValue) === false) {
            throw new \RuntimeException('Unknown end block: ' . $nodeValue, $node->line);
        }

        $i = $this->findSection($open);

        unset($open[$i]);

        return function (Invocation $invocation, callable $next) use ($node) {
            $newInvocation = $invocation->withBlockFlag(false);
            yield from $next($newInvocation);
        };
    }

    protected function findSection(array $open, $name = self::LAST_OPEN)
    {
        foreach ($open as $i => $item) {
            assert($item instanceof Node);
            $item = explode(' ', $item->value);

            if ($item[0] === $name) {
                return $i;
            }
        }

        if ($name == self::LAST_OPEN) {
            return $i;
        }

        return false;
    }

    protected function generateVariable(Node $node, &$open, bool $escape = false): \Closure
    {
        $nodeValue = trim($node->value);

        [$name, $args, $hash] = $this->parseArguments($nodeValue);

        $helper = isset($this->helpers[$name]) ?? null;

        if ($helper) {
            return function (Invocation $data, callable $next) use ($name, $args, $hash) {
                $generator = $this->helpers[$name]($data->withArgs($args)->withHash($hash), $next);
                while ($generator->valid()) {
                    $chunk = $generator->current();
                    $data->append($chunk);
                    yield $chunk;
                    $generator->next();
                }

                return $generator->getReturn();
            };
        }

        //it's a value ?
        $value = str_replace(['[', ']', '(', ')'], '', $nodeValue);
        $value = str_replace("'", '\\\'', $value);

        return function (Invocation $data, callable $next) use ($value, $escape) {
            $chunk = $this->getValue($value, $data->data, $escape);
            $data->append($chunk);
            yield $chunk;
            yield from $next($data);
        };
    }

    private function getValue(string $key, array|\ArrayAccess $data, bool $escape = false): string
    {
        if (!isset($data[$key])) {
            return '';
        }
        $value = $data[$key];

        match (true) {
            is_scalar($value) => $escapedValue = (string)$value,
            is_array($value) => $escapedValue = json_encode($value),
            default => $escapedValue = get_class($value),
        };

        if ($escape) {
            // Assuming a simple HTML escaping mechanism for demonstration purposes
            return htmlspecialchars($escapedValue, ENT_QUOTES, 'UTF-8');
        }

        return $escapedValue;
    }

    private function generateText(Node $node): \Closure
    {
        return function (Invocation $data, callable $next) use ($node) {
            $chunk = $node->value;
            $data->append($chunk);
            yield $chunk;
            yield from $next($data);
        };
    }

    private function parseArguments($string): array
    {
        $args = [];
        $hash = [];

        $regex = [
            '([a-zA-Z0-9]+\="[^"]*")',      // cat="meow"
            '([a-zA-Z0-9]+\=\'[^\']*\')',   // mouse='squeak squeak'
            '([a-zA-Z0-9]+\=[a-zA-Z0-9\.]+)', // dog=false
            '("[^"]*")',                    // "some\'thi ' ng"
            '(\'[^\']*\')',                 // 'some"thi " ng'
            '([^\s]+)',                      // <any group with no spaces>
        ];

        preg_match_all('#' . implode('|', $regex) . '#is', $string, $matches);

        $stringArgs = $matches[0];
        $name = array_shift($stringArgs);

        $hashRegex = [
            '([a-zA-Z0-9]+\="[^"]*")',      // cat="meow"
            '([a-zA-Z0-9]+\=\'[^\']*\')',   // mouse='squeak squeak'
            '([a-zA-Z0-9]+\=[a-zA-Z0-9\.]+)', // dog=false
        ];

        foreach ($stringArgs as $arg) {
            //if it's an attribute
            if (
                !(substr($arg, 0, 1) === "'" && substr($arg, -1) === "'")
                && !(substr($arg, 0, 1) === '"' && substr($arg, -1) === '"')
                && preg_match('#' . implode('|', $hashRegex) . '#is', $arg)
            ) {
                [$hashKey, $hashValue] = explode('=', $arg, 2);
                $hash[$hashKey] = $this->parseArgument($hashValue);
                continue;
            }

            $args[] = $this->parseArgument($arg);
        }

        return [$name, $args, $hash];
    }

    protected function parseArgument($arg)
    {
        // if it's null
        if (strtolower($arg) === 'null') {
            return null;
        } elseif (strtolower($arg) === 'true') {
            return true;
        } elseif (strtolower($arg) === 'false') {
            return false;
        }

        // Check for integer and float
        if (is_numeric($arg)) {
            if (str_contains($arg, '.')) {
                return (float)$arg;
            } else {
                return (int)$arg;
            }
        }

        return $arg;
    }

    //private function compileInterpolations($code, int $index)
    //{
    //    $replacement = sprintf(
    /*        '<?php echo $this->resolve("$1", %d); ?>',*/
    //        $index
    //    );
    //    $pattern = '~'.preg_quote(self::DEL[0], '~').'\s*(.+?)\s*'.preg_quote(self::DEL[1], '~').'~is';
    //
    //    return preg_replace($pattern, $replacement, $code);
    //}
    //
    //private function compile(string $code)
    //{
    //    $pattern = '~('.preg_quote(self::DEL[0], '~').'\s*.+?\s*'.preg_quote(self::DEL[1], '~').')~is';
    //
    //    return preg_split(
    //        $pattern,
    //        $code,
    //        -1,
    //        PREG_SPLIT_DELIM_CAPTURE
    //    );
    //}
}
