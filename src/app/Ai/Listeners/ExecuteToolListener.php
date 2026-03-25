<?php

namespace App\Ai\Listeners;

use App\Ai\Events\ToolCalled;
use App\Ai\Events\ToolResultReceived;
use App\Ai\Tools\ToolRegistry;
use Laravel\Ai\Tools\Request;
use Illuminate\Support\Facades\Log;

class ExecuteToolListener
{
    public function __construct(
        private ToolRegistry $toolRegistry
    ) {}

    public function handle(ToolCalled $event): void
    {
        Log::info("ИИ: Выполнение инструмента...", ['tool' => $event->step->tool]);

        $toolName = $event->step->tool;
        $parameters = $event->step->parameters;

        $tool = $this->toolRegistry->get($toolName);

        if (!$tool) {
            $result = "Error: Tool '{$toolName}' not found in registry.";
        } else {
            try {
                $request = new Request($parameters);
                $result = $tool->handle($request);
            } catch (\Throwable $e) {
                $result = "Error executing tool '{$toolName}': " . $e->getMessage();
            }
        }

        ToolResultReceived::dispatch($event->step, (string) $result, $event->context);
    }
}
