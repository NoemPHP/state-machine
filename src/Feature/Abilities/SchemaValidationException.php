<?php

declare(strict_types=1);

namespace Noem\State\Feature\Abilities;

/**
 * Exception thrown when ability parameters fail schema validation
 */
class SchemaValidationException extends \RuntimeException
{
    /**
     * @param array<array{path: string, message: string}> $errors
     */
    public function __construct(
        string $message,
        private readonly array $errors = []
    ) {
        $errorDetails = '';
        if (!empty($errors)) {
            $errorDetails = "\nValidation errors:\n" . implode("\n", array_map(
                fn(array $error) => sprintf('[%s] %s', $error['path'] ?? '', $error['message'] ?? ''),
                $errors
            ));
        }

        parent::__construct($message . $errorDetails);
    }

    /**
     * Get structured validation error details
     *
     * @return array<array{path: string, message: string}>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
