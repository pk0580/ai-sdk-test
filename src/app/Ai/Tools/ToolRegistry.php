<?php

namespace App\Ai\Tools;

use Laravel\Ai\Contracts\Tool;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;

class ToolRegistry
{
    /** @var array<string, Tool> */
    private array $tools = [];

    public function register(string $name, Tool $tool): void
    {
        $this->tools[$name] = $tool;
    }

    public function get(string $name): ?Tool
    {
        return $this->tools[$name] ?? null;
    }

    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
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

            $parameters = [
                'type' => 'object',
                'properties' => [],
                'required' => [],
            ];

            foreach ($schema as $paramName => $type) {
                $parameters['properties'][$paramName] = $type->toArray();

                // Используем Reflection, так как свойство $required защищено и нет публичного метода isRequired
                $reflection = new \ReflectionProperty($type, 'required');
                $reflection->setAccessible(true);
                if ($reflection->getValue($type) === true) {
                    $parameters['required'][] = $paramName;
                }
            }

            $definitions[$name] = [
                'name' => $name,
                'description' => (string) $tool->description(),
                'parameters' => $parameters,
            ];
        }

        return $definitions;
    }
}
