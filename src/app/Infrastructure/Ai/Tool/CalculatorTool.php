<?php

declare(strict_types=1);

namespace App\Infrastructure\Ai\Tool;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

final class CalculatorTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Calculates a mathematical expression.';
    }

    public function handle(Request $request): Stringable|string
    {
        $expression = (string) $request->string('expression');
        Log::info("Tool [CalculatorTool]: called with expression='{$expression}'");

        if (empty($expression)) {
            return 'Error: Expression is empty.';
        }

        if (strlen($expression) > 255) {
            return 'Error: Expression too long.';
        }

        try {
            $expression = preg_replace('/[^0-9+\-*\/(). ]/', '', $expression);

            $depth = 0;
            $maxDepth = 10;
            for ($i = 0; $i < strlen($expression); $i++) {
                if ($expression[$i] === '(') {
                    $depth++;
                } elseif ($expression[$i] === ')') {
                    $depth--;
                }
                if ($depth > $maxDepth) {
                    return 'Error: Expression too complex (too many nested parentheses).';
                }
                if ($depth < 0) {
                    return 'Error: Unbalanced parentheses.';
                }
            }
            if ($depth !== 0) {
                return 'Error: Unbalanced parentheses.';
            }

            $operatorsCount = preg_match_all('/[+\-*\/]/', $expression);
            if ($operatorsCount > 50) {
                return 'Error: Expression too complex (too many operators).';
            }

            if (!preg_match('/[0-9]/', $expression)) {
                return 'Error: Invalid expression (no numbers found).';
            }

            if (preg_match('/[+\-*\/]$/', trim($expression))) {
                return 'Error: Expression ends with an operator.';
            }

            if (str_contains($expression, '**')) {
                return 'Error: Exponentiation (**) is not allowed.';
            }

            $expression = preg_replace('/(?<=[0-9])\s*\.\s*(?=[0-9])/', '.', $expression);
            $expression = preg_replace('/(?<![0-9])\./', '', $expression);
            $expression = preg_replace('/\.(?![0-9])/', '', $expression);

            if (preg_match('/\/0(\.0*)?(?![0-9])/', $expression)) {
                return 'Error: Division by zero.';
            }

            set_error_handler(static function ($errno, $errstr) {
                throw new \ErrorException($errstr, $errno);
            });

            try {
                $result = eval("return $expression;");
            } finally {
                restore_error_handler();
            }

            if ($result === false && error_get_last()) {
                return 'Error: Syntax error in expression.';
            }

            return (string) $result;
        } catch (\Throwable $e) {
            return 'Error: ' . $e->getMessage();
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'expression' => $schema->string()
                ->description('The mathematical expression to evaluate (e.g., "2 + 2").')
                ->required(),
        ];
    }
}
