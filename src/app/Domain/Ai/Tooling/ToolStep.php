<?php

declare(strict_types=1);

namespace App\Domain\Ai\Tooling;

final readonly class ToolStep
{
    public function __construct(
        public string  $tool,
        public array   $parameters = [],
        public ?string $description = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            tool: (string) $data['tool'],
            parameters: (array) ($data['parameters'] ?? []),
            description: isset($data['description']) ? (string) $data['description'] : null,
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
