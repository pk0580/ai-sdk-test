<?php

declare(strict_types=1);

namespace App\Domain\Ai\Conversation;

use InvalidArgumentException;

final class AgentName
{
    public const string RESEARCH = 'research';
    public const string SUMMARY = 'summary';

    private function __construct(public readonly string $value) {}

    public static function fromString(string $value): self
    {
        $value = trim($value);

        if ($value === '') {
            throw new InvalidArgumentException('Agent name must not be empty');
        }

        return new self($value);
    }

    public static function research(): self
    {
        return new self(self::RESEARCH);
    }

    public static function summary(): self
    {
        return new self(self::SUMMARY);
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
