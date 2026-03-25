<?php

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class CalculatorTool implements Tool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Calculates a mathematical expression.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $expression = $request->string('expression');

        try {
            // Remove any characters that are not numbers or basic operators
            $expression = preg_replace('/[^0-9+\-*\/(). ]/', '', $expression);
            $result = eval("return $expression;");
            return (string) $result;
        } catch (\Throwable $e) {
            return "Error: " . $e->getMessage();
        }
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'expression' => $schema->string()
                ->description('The mathematical expression to evaluate (e.g., "2 + 2").')
                ->required(),
        ];
    }
}
