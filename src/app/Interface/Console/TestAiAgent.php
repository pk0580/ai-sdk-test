<?php

declare(strict_types=1);

namespace App\Interface\Console;

use App\Application\Ai\Conversation\DTO\StartConversationInput;
use App\Application\Ai\Conversation\UseCase\StartConversationUseCase;
use Illuminate\Console\Command;

final class TestAiAgent extends Command
{
    protected $signature = 'ai:test {message=Hello}';

    protected $description = 'Test AI Agent Reactive Chain';

    public function handle(StartConversationUseCase $useCase): int
    {
        $message = (string) $this->argument('message');
        $this->info("Starting Workflow via use case for: {$message}");

        $output = $useCase->execute(new StartConversationInput(message: $message));

        $this->info('Workflow initiated. Session id: ' . $output->sessionId());
        $this->info('Check logs for the reactive execution progress.');

        return Command::SUCCESS;
    }
}
