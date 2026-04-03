<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Ai\Core\Supervisor;

class TestAiAgent extends Command
{
    protected $signature = 'ai:test {message=Hello}';
    protected $description = 'Test AI Agent Reactive Chain';

    public function handle(Supervisor $supervisor)
    {
        $message = $this->argument('message');
        $this->info("Starting Workflow via Supervisor for: $message");

        $state = $supervisor->handle($message);

        $this->info("Workflow initiated. Current history count: " . count($state->history));
        $this->info("Check logs for the reactive execution progress.");
    }
}
