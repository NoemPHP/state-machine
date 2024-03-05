<?php

declare(strict_types=1);

namespace Noem\State\Helper;

class PhpEvalHelper
{

    public function __invoke(string $content): mixed
    {
        try {
            return eval($content.';');
        } catch (\Throwable $exception) {
            throw new \RuntimeException(
                $this->createFragmentForException($content, $exception),
                0,
                $exception
            );
        }
    }

    private function createFragmentForException(string $content, \Throwable $exception)
    {
        $lines = explode(
            "\n",
            $content
        ); // Split by newline characters (\n) into array of strings (i.e., the individual lines)
        $result = PHP_EOL;
        foreach ($lines as $lineNumber => $line) {
            $isErrorLine = $exception->getLine() === $lineNumber + 1;
            $prefix = $isErrorLine
                ? '>'
                : ' ';
            $result .= "{$prefix} {$lineNumber} |{$line}\n";
            // Replace `echo` statement with your desired action per line (process or output it in some way).
        }

        return $result;
    }
}
