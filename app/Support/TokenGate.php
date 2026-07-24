<?php

namespace App\Support;

/**
 * Outcome of an AI token pre-flight check: whether the user may send a message
 * and, when blocked, why plus a user-facing message.
 */
final readonly class TokenGate
{
    public function __construct(
        public bool $allowed,
        public ?string $reason = null,
        public ?string $message = null,
    ) {}

    public static function allow(): self
    {
        return new self(true);
    }

    public static function block(string $reason, string $message): self
    {
        return new self(false, $reason, $message);
    }
}
