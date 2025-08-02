<?php

declare(strict_types=1);

namespace Noem\State\Feature\Loader\Helper;

class PhpEvalHelper
{

    public function __invoke(string $content): mixed
    {
        // Set a custom error handler to convert PHP errors to exceptions
        set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline): bool {
            throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
        });
        try {
            return eval($content.';');
        } catch (\Throwable $exception) {
            throw new \RuntimeException(
                $exception->getMessage().PHP_EOL.
                $this->createFragmentForException($content, $exception),
                0,
                $exception
            );
        } finally {
            // Restore the original error handler to avoid side effects
            restore_error_handler();
        }
    }

    private function createFragmentForException(string $content, \Throwable $exception): string
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
