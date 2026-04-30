<?php

declare(strict_types=1);

namespace App\Domain\Ai\Conversation;

use InvalidArgumentException;

final readonly class SessionId
{
    private function __construct(public string $value) {}

    public static function fromString(string $value): self
    {
        $value = trim($value);

        if ($value === '') {
            throw new InvalidArgumentException('Session id must not be empty');
        }

        return new self($value);
    }

    public static function generate(): self
    {
        return new self(uniqid('sess_', true));
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
