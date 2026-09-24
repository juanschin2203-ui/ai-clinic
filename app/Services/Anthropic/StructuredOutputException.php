<?php

declare(strict_types=1);

namespace App\Services\Anthropic;

use RuntimeException;

/**
 * Thrown when Anthropic's response can't be parsed into the schema the
 * stage asked for. Carries the raw text for debugging. The pipeline
 * orchestrator retries up to 3 times before escalating to `needsReview`.
 */
class StructuredOutputException extends RuntimeException
{
    public function __construct(
        public readonly string $rawText,
        string $message = 'Anthropic response did not match the expected JSON schema.',
    ) {
        parent::__construct($message);
    }
}
