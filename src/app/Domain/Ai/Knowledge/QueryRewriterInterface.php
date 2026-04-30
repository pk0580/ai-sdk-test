<?php

declare(strict_types=1);

namespace App\Domain\Ai\Knowledge;

interface QueryRewriterInterface
{
    public function rewrite(string $query): string;
}
