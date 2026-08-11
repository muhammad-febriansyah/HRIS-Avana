<?php

namespace App\Services;

use RuntimeException;
use Throwable;

final class FaceRecognitionException extends RuntimeException
{
    public function __construct(
        public readonly string $reason,
        string $message,
        public readonly bool $unavailable = false,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
