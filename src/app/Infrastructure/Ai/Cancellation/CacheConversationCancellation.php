<?php

declare(strict_types=1);

namespace App\Infrastructure\Ai\Cancellation;

use App\Domain\Ai\Conversation\ConversationCancellationInterface;
use App\Domain\Ai\Conversation\SessionId;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

final class CacheConversationCancellation implements ConversationCancellationInterface
{
    private const int TTL_SECONDS = 600;

    public function __construct(private readonly CacheRepository $cache) {}

    public function isCancelled(SessionId $sessionId): bool
    {
        return $this->cache->has($this->key($sessionId));
    }

    public function cancel(SessionId $sessionId): void
    {
        $this->cache->put($this->key($sessionId), true, self::TTL_SECONDS);
    }

    public function clear(SessionId $sessionId): void
    {
        $this->cache->forget($this->key($sessionId));
    }

    private function key(SessionId $sessionId): string
    {
        return 'cancel_' . $sessionId->value;
    }
}
