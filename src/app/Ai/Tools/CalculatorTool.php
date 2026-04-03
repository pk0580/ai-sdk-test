<?php

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
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
        Log::info("Tool [CalculatorTool]: Вызов с параметром expression='{$expression}'");

        if (empty($expression)) {
            return "Error: Expression is empty.";
        }

        if (strlen($expression) > 255) {
            return "Error: Expression too long.";
        }

        try {
            // Remove any characters that are not numbers or basic operators
            $expression = preg_replace('/[^0-9+\-*\/(). ]/', '', $expression);

            // Check for excessive nesting of parentheses
            $depth = 0;
            $maxDepth = 10;
            for ($i = 0; $i < strlen($expression); $i++) {
                if ($expression[$i] === '(') {
                    $depth++;
                } elseif ($expression[$i] === ')') {
                    $depth--;
                }
                if ($depth > $maxDepth) {
                    return "Error: Expression too complex (too many nested parentheses).";
                }
                if ($depth < 0) {
                    return "Error: Unbalanced parentheses.";
                }
            }
            if ($depth !== 0) {
                return "Error: Unbalanced parentheses.";
            }
            // Limit number of operators to prevent excessive computation
            $operatorsCount = preg_match_all('/[+\-*\/]/', $expression);
            if ($operatorsCount > 50) {
                return "Error: Expression too complex (too many operators).";
            }

            // Check if there are any numbers left in the expression
            if (!preg_match('/[0-9]/', $expression)) {
                return "Error: Invalid expression (no numbers found).";
            }

            // Simple validation: should not end with operator or start with unexpected operators (except - and +)
            if (preg_match('/[+\-*\/]$/', trim($expression))) {
                return "Error: Expression ends with an operator.";
            }

            // Prevent exponentiation (**) if it can lead to massive numbers that take long to compute
            if (str_contains($expression, '**')) {
                return "Error: Exponentiation (**) is not allowed.";
            }

            // Fix for expressions like "3 . 14" which might be interpreted as "3 . 14" (concatenation in some contexts, but here just weird)
            // or just ensure that dots are part of numbers.
            // Actually, in eval("return 3 . 14;"), PHP treats . as string concatenation if there are spaces.
            // "3 . 14" becomes "314".
            // To prevent this, we can remove spaces between digits and dots.
            $expression = preg_replace('/(?<=[0-9])\s*\.\s*(?=[0-9])/', '.', $expression);
            // And remove other dots that are not part of numbers
            $expression = preg_replace('/(?<![0-9])\./', '', $expression);
            $expression = preg_replace('/\.(?![0-9])/', '', $expression);

            // Prevent division by zero if it can be caught before eval
            if (preg_match('/\/0(\.0*)?(?![0-9])/', $expression)) {
                return "Error: Division by zero.";
            }

            // Set custom error handler to catch warnings from eval (like division by zero in older PHP)
            set_error_handler(function($errno, $errstr) {
                throw new \ErrorException($errstr, $errno);
            });

            try {
                $result = eval("return $expression;");
            } finally {
                restore_error_handler();
            }

            if ($result === false && error_get_last()) {
                return "Error: Syntax error in expression.";
            }

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
