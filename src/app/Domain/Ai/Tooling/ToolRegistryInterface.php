<?php

declare(strict_types=1);

namespace App\Domain\Ai\Tooling;

interface ToolRegistryInterface
{
    public function has(string $name): bool;

    public function get(string $name): ?object;

    /** @return array<string, object> */
    public function all(): array;

    /** @return array<string, array<string, mixed>> JSON-Schema style descriptors */
    public function getToolsDefinitions(): array;
}
