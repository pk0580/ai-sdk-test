<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Ai\Core\LoopController;
use App\Ai\Core\Planner;
use App\Ai\Core\Reflector;
use App\Ai\Tools\ToolRegistry;
use App\Ai\Agents\CheapAnonymousAgent;
use App\Ai\Agents\SmartAnonymousAgent;
use App\Models\AiLog;
use Laravel\Ai\Contracts\Tool;
use Mockery;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AgentLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_loop_controller_logs_steps_and_latency()
    {
        // 1. Настраиваем заглушки
        $toolRegistry = new ToolRegistry();
        $mockTool = Mockery::mock(Tool::class);
        $mockTool->shouldReceive('handle')->andReturn('Tool result');
        $mockTool->shouldReceive('description')->andReturn('Test tool description');
        $mockTool->shouldReceive('schema')->andReturn([]);
        $toolRegistry->register('test_tool', $mockTool);

        CheapAnonymousAgent::fake([
            // Planner
            json_encode([
                'steps' => [
                    [
                        'tool' => 'test_tool',
                        'parameters' => ['p' => 'v'],
                        'description' => 'Test OrchestrationStep'
                    ]
                ]
            ]),
            // Reflector
            json_encode([
                'decision' => 'finish',
                'thought' => 'All done.',
                'next_suggestion' => null
            ])
        ]);

        SmartAnonymousAgent::fake([
            "Final Response"
        ]);

        $planner = new Planner($toolRegistry);
        $reflector = new Reflector();
        $controller = new LoopController($planner, $reflector, $toolRegistry);

        // 2. Выполняем задачу
        $controller->execute("Hello log");

        // 3. Проверяем наличие логов в БД
        $this->assertDatabaseHas('ai_logs', [
            'agent_name' => 'LoopController',
            'action' => 'test_tool',
            'thought' => 'Test OrchestrationStep'
        ]);

        $this->assertDatabaseHas('ai_logs', [
            'agent_name' => 'Reflector',
            'action' => 'finish',
            'thought' => 'All done.'
        ]);

        // Проверяем latency
        $log = AiLog::first();
        $this->assertNotNull($log->latency);
        $this->assertGreaterThan(0, $log->latency);

        // Проверяем session_id
        $logs = AiLog::all();
        $this->assertGreaterThanOrEqual(2, $logs->count());
        $this->assertEquals($logs[0]->session_id, $logs[1]->session_id);
    }
}
