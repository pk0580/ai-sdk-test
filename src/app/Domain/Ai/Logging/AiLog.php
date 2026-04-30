<?php

declare(strict_types=1);

namespace App\Domain\Ai\Logging;

use DateTimeImmutable;

final readonly class AiLog
{
    public function __construct(
        public ?string           $sessionId,
        public string            $agentName,
        public ?string           $thought,
        public ?string           $action,
        public array             $input,
        public ?string           $output,
        public ?float            $latency,
        public DateTimeImmutable $occurredAt,
    ) {}
}
