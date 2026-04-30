<?php

declare(strict_types=1);

namespace App\Infrastructure\Ai\Tool;

use App\Domain\Ai\Tooling\ToolRegistryInterface;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Contracts\Tool;

final class InMemoryToolRegistry implements ToolRegistryInterface
{
    /** @var array<string, Tool> */
    private array $tools = [];

    public function register(string $name, Tool $tool): void
    {
        $this->tools[$name] = $tool;
    }

    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }

    public function get(string $name): ?object
    {
        return $this->tools[$name] ?? null;
    }

    /** @return array<string, Tool> */
    public function all(): array
    {
        return $this->tools;
    }

    public function getToolsDefinitions(): array
    {
        $definitions = [];
        $jsonSchema = new JsonSchemaTypeFactory();

        foreach ($this->tools as $name => $tool) {
            $schema = $tool->schema($jsonSchema);

            $properties = [];
            $required = [];

            foreach ($schema as $paramName => $type) {
                $properties[$paramName] = $type->toArray();

                $isRequired = (fn () => $this->required)->call($type);
                if ($isRequired === true) {
                    $required[] = $paramName;
                }
            }

            $definitions[$name] = [
                'name' => $name,
                'description' => (string) $tool->description(),
                'parameters' => [
                    'type' => 'object',
                    'properties' => $properties,
                    'required' => $required,
                ],
            ];
        }

        return $definitions;
    }
}
