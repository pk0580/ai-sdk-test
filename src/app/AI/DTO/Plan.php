<?php

namespace App\AI\DTO;

use Illuminate\Support\Collection;

class Plan implements DTOInterface
{
    /** @var Collection<int, Step> */
    public Collection $steps;

    public function __construct(array $steps = [])
    {
        $this->steps = collect($steps)->map(fn ($step) =>
            $step instanceof Step ? $step : Step::fromArray($step)
        );
    }

    public static function fromArray(array $data): self
    {
        return new self($data['steps'] ?? []);
    }

    public function toArray(): array
    {
        return [
            'steps' => $this->steps->map(fn(Step $step) => $step->toArray())->toArray(),
        ];
    }
}
