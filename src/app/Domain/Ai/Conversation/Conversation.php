<?php

declare(strict_types=1);

namespace App\Domain\Ai\Conversation;

use DateTimeImmutable;

/**
 * Aggregate root that represents a single AI conversation/workflow run.
 *
 * Replaces the previous Eloquent/Laravel-flavored AgentState. Instances are
 * immutable: every state transition returns a new instance.
 */
final class Conversation
{
    public const int HISTORY_LIMIT = 10;

    /** @param HistoryEntry[] $history */
    private function __construct(
        public readonly SessionId $sessionId,
        public readonly string $input,
        public readonly ?PlanStep $step,
        public readonly ?string $context,
        public readonly array $history,
    ) {}

    public static function start(string $input, ?SessionId $sessionId = null): self
    {
        return new self(
            sessionId: $sessionId ?? SessionId::generate(),
            input: $input,
            step: null,
            context: null,
            history: [],
        );
    }

    /** @param HistoryEntry[] $history */
    public static function reconstitute(
        SessionId $sessionId,
        string $input,
        ?PlanStep $step,
        ?string $context,
        array $history,
    ): self {
        return new self($sessionId, $input, $step, $context, $history);
    }

    public function withStepResult(PlanStep $step, string $result, bool $success): self
    {
        $entry = new HistoryEntry(
            agent: $step->agent,
            task: $step->task,
            result: $result,
            success: $success,
            occurredAt: new DateTimeImmutable(),
        );

        return new self(
            sessionId: $this->sessionId,
            input: $this->input,
            step: null,
            context: $this->context,
            history: [...$this->history, $entry],
        );
    }

    public function isFinished(): bool
    {
        $last = $this->lastEntry();

        if ($last === null) {
            return false;
        }

        if ($last->agent === AgentName::SUMMARY && $last->success) {
            return true;
        }

        return str_contains($last->result, '[FINISHED]')
            || str_contains($last->result, '[SUMMARY_FINISHED]')
            || str_contains($last->result, '[RESEARCH_FINISHED]');
    }

    public function reachedHistoryLimit(): bool
    {
        return count($this->history) >= self::HISTORY_LIMIT;
    }

    public function historyCount(): int
    {
        return count($this->history);
    }

    public function lastEntry(): ?HistoryEntry
    {
        return $this->history === [] ? null : $this->history[array_key_last($this->history)];
    }

    /** @return array<int, array<string, mixed>> */
    public function historyAsArrays(): array
    {
        return array_map(static fn (HistoryEntry $e) => $e->toArray(), $this->history);
    }
}
