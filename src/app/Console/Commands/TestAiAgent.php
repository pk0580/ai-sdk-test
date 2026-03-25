<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Ai\Events\UserMessageReceived;

class TestAiAgent extends Command
{
    protected $signature = 'ai:test {message=Hello}';
    protected $description = 'Test AI Agent Event Chain';

    public function handle()
    {
        $message = $this->argument('message');
        $this->info("Dispatching UserMessageReceived: $message");

        UserMessageReceived::dispatch($message, ['session_id' => uniqid()]);

        $this->info("Done. Check logs for results.");
    }
}
