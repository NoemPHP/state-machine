<?php

declare(strict_types=1);

namespace Noem\State\Feature\Loader\Helper;

class PhpEvalHelper
{
    private array $scopeVariables = [];
    
    /**
     * Set variables that should be available in the eval scope
     */
    public function setScopeVariables(array $variables): void
    {
        $this->scopeVariables = $variables;
    }
    
    public function __invoke(string $content): mixed
    {
        // Extract scope variables into local scope
        extract($this->scopeVariables);
        
        // Set a custom error handler to convert PHP errors to exceptions
        set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline): bool {
            throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
        });
        try {
            $result = eval($content . ';');
            
            // If the result is a closure, unbind $this so it can be bound to the proper context later
            // Only unbind if the closure doesn't use $this (static closures)
            if ($result instanceof \Closure) {
                $reflection = new \ReflectionFunction($result);
                // Only unbind if closure doesn't use $this (check if it has a scope class)
                if ($reflection->getClosureThis() === null && $reflection->getClosureScopeClass() === null) {
                    // Closure doesn't use $this, safe to unbind
                    $result = \Closure::bind($result, null);
                }
                // Otherwise leave it as-is - it will be bound by the framework when needed
            }
            
            return $result;
        } catch (\Throwable $exception) {
            throw new \RuntimeException(
                $exception->getMessage() . PHP_EOL .
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
