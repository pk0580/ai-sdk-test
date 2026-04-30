<?php

declare(strict_types=1);

namespace App\Domain\Ai\Tooling;

final readonly class Plan
{
    /** @var ToolStep[] */
    public array $steps;

    /** @param array<int, ToolStep|array<string, mixed>> $steps */
    public function __construct(array $steps = [])
    {
        $this->steps = array_values(array_map(
            static fn ($step) => $step instanceof ToolStep ? $step : ToolStep::fromArray($step),
            $steps,
        ));
    }

    public static function fromArray(array $data): self
    {
        return new self($data['steps'] ?? []);
    }

    public function toArray(): array
    {
        return [
            'steps' => array_map(static fn (ToolStep $s) => $s->toArray(), $this->steps),
        ];
    }

    public function isEmpty(): bool
    {
        return $this->steps === [];
    }

    public function first(): ?ToolStep
    {
        return $this->steps[0] ?? null;
    }
}
