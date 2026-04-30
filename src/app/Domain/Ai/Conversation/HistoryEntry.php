<?php

declare(strict_types=1);

namespace App\Domain\Ai\Conversation;

use DateTimeImmutable;

final readonly class HistoryEntry
{
    public function __construct(
        public string            $agent,
        public string            $task,
        public string            $result,
        public bool              $success,
        public DateTimeImmutable $occurredAt,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            agent: (string) ($data['agent'] ?? ''),
            task: (string) ($data['task'] ?? ''),
            result: (string) ($data['result'] ?? ''),
            success: (bool) ($data['success'] ?? false),
            occurredAt: isset($data['timestamp'])
                ? new DateTimeImmutable((string) $data['timestamp'])
                : new DateTimeImmutable(),
        );
    }

    public function toArray(): array
    {
        return [
            'agent' => $this->agent,
            'task' => $this->task,
            'result' => $this->result,
            'success' => $this->success,
            'timestamp' => $this->occurredAt->format('Y-m-d H:i:s'),
        ];
    }
}
