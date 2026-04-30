<?php

declare(strict_types=1);

namespace App\Domain\Ai\Tooling;

use InvalidArgumentException;

final readonly class ToolName
{
    private function __construct(public string $value) {}

    public static function fromString(string $value): self
    {
        $value = trim($value);

        if ($value === '') {
            throw new InvalidArgumentException('Tool name must not be empty');
        }

        return new self($value);
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
