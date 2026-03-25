<?php

namespace App\AI\DTO;

class Step implements DTOInterface
{
    public function __construct(
        public string $tool,
        public array $parameters = [],
        public ?string $description = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            $data['tool'],
            $data['parameters'] ?? [],
            $data['description'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'tool' => $this->tool,
            'parameters' => $this->parameters,
            'description' => $this->description,
        ];
    }
}
