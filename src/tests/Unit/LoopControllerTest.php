<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Ai\Core\LoopController;
use App\Ai\Core\Planner;
use App\Ai\Core\Reflector;
use App\Ai\Tools\ToolRegistry;
use App\Ai\Agents\CheapAnonymousAgent;
use App\Ai\Agents\SmartAnonymousAgent;
use Laravel\Ai\Contracts\Tool;
use Mockery;

class LoopControllerTest extends TestCase
{
    public function test_loop_controller_executes_plan_and_handles_reflection()
    {
        // 1. Настраиваем заглушки для инструментов
        $toolRegistry = new ToolRegistry();
        $mockTool = Mockery::mock(Tool::class);
        $mockTool->shouldReceive('handle')->andReturn('Tool result');
        // Добавляем описание и схему, чтобы Planner не упал при генерации промпта (хотя мы его фейкаем)
        $mockTool->shouldReceive('description')->andReturn('Test tool description');
        $mockTool->shouldReceive('schema')->andReturn([]);
        $toolRegistry->register('test_tool', $mockTool);

        // 2. Настраиваем фейковые ответы для LLM (через CheapAnonymousAgent::fake и SmartAnonymousAgent::fake)
        CheapAnonymousAgent::fake([
            // Ответ для Planner (начальный план)
            json_encode([
                'steps' => [
                    [
                        'tool' => 'test_tool',
                        'parameters' => ['param' => 'value'],
                        'description' => 'Initial step'
                    ]
                ]
            ]),
            // Ответ для Reflector (завершение)
            json_encode([
                'decision' => 'finish',
                'thought' => 'Information is sufficient.',
                'next_suggestion' => null
            ])
        ]);

        SmartAnonymousAgent::fake([
            // Ответ для Responder (финальный ответ)
            "Final human-friendly response."
        ]);

        $planner = new Planner($toolRegistry);
        $reflector = new Reflector($toolRegistry);
        $controller = new LoopController($planner, $reflector, $toolRegistry);

        $result = $controller->execute("Test message");

        $this->assertEquals("Final human-friendly response.", $result);
    }

    public function test_loop_controller_can_continue_loop_based_on_reflection()
    {
        $toolRegistry = new ToolRegistry();
        $mockTool = Mockery::mock(Tool::class);
        $mockTool->shouldReceive('handle')->andReturn('First result', 'Second result');
        $mockTool->shouldReceive('description')->andReturn('Test tool description');
        $mockTool->shouldReceive('schema')->andReturn([]);
        $toolRegistry->register('test_tool', $mockTool);

        CheapAnonymousAgent::fake([
            // 1. Planner response
            json_encode([
                'steps' => [
                    ['tool' => 'test_tool', 'parameters' => ['step' => 1], 'description' => 'OrchestrationStep 1']
                ]
            ]),
            // 2. Reflector response (continue)
            json_encode([
                'decision' => 'continue',
                'thought' => 'Need more data.',
                'next_suggestion' => 'Try test_tool again'
            ]),
            // 3. Planner::parseStep response for next_suggestion
            json_encode([
                'steps' => [
                    ['tool' => 'test_tool', 'parameters' => ['step' => 2], 'description' => 'Try test_tool again']
                ]
            ]),
            // 4. Reflector response (finish)
            json_encode([
                'decision' => 'finish',
                'thought' => 'Now it is enough.',
                'next_suggestion' => null
            ])
        ]);

        SmartAnonymousAgent::fake([
            // 5. Responder response
            "Multi-step final response."
        ]);

        $planner = new Planner($toolRegistry);
        $reflector = new Reflector($toolRegistry);
        $controller = new LoopController($planner, $reflector, $toolRegistry, 3);

        $result = $controller->execute("Test multi-step");

        $this->assertEquals("Multi-step final response.", $result);
    }
}
