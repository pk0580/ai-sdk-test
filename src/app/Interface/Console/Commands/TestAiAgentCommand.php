<?php

declare(strict_types=1);

namespace App\Interface\Console\Commands;

use App\Application\Ai\Conversation\StartConversation\StartConversationAction;
use App\Application\Ai\Conversation\StartConversation\StartConversationData;
use Illuminate\Console\Command;

final class TestAiAgentCommand extends Command
{
    protected $signature = 'ai:test {message=Hello}';

    protected $description = 'Test AI Agent Reactive Chain';

    public function handle(StartConversationAction $action): int
    {
        $message = (string) $this->argument('message');
        $this->info("Starting Workflow via action for: {$message}");

        $output = $action->handle(new StartConversationData(message: $message));

        $this->info('Workflow initiated. Session id: ' . $output->sessionId());
        $this->info('Check logs for the reactive execution progress.');

        return Command::SUCCESS;
    }
}
