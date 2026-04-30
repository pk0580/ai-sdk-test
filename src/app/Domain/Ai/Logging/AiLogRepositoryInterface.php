<?php

declare(strict_types=1);

namespace App\Domain\Ai\Logging;

interface AiLogRepositoryInterface
{
    public function save(AiLog $log): void;
}
