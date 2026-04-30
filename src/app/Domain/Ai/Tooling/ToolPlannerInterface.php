<?php

declare(strict_types=1);

namespace App\Domain\Ai\Tooling;

interface ToolPlannerInterface
{
    public function generate(string $message): Plan;

    public function parseStep(string $suggestion): ?ToolStep;
}
