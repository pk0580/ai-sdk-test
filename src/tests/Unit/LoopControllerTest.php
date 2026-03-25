<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Ai\Core\LoopController;
use App\Ai\Core\Planner;
use App\Ai\Core\Reflector;
use App\Ai\Tools\ToolRegistry;
use Laravel\Ai\AnonymousAgent;
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

        // 2. Настраиваем фейковые ответы для LLM (через AnonymousAgent::fake)
        AnonymousAgent::fake([
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
        ])->preventStrayPrompts();

        $planner = new Planner($toolRegistry);
        $reflector = new Reflector();
        $controller = new LoopController($planner, $reflector, $toolRegistry);

        $result = $controller->execute("Test message");

        $this->assertStringContainsString('Финальный анализ: Information is sufficient.', $result);
        $this->assertStringContainsString('[test_tool] Initial step', $result);
        $this->assertStringContainsString('Результат: Tool result', $result);
    }

    public function test_loop_controller_can_continue_loop_based_on_reflection()
    {
        $toolRegistry = new ToolRegistry();
        $mockTool = Mockery::mock(Tool::class);
        $mockTool->shouldReceive('handle')->andReturn('First result', 'Second result');
        $mockTool->shouldReceive('description')->andReturn('Test tool description');
        $mockTool->shouldReceive('schema')->andReturn([]);
        $toolRegistry->register('test_tool', $mockTool);

        AnonymousAgent::fake([
            // 1. Planner response
            json_encode([
                'steps' => [
                    ['tool' => 'test_tool', 'parameters' => ['step' => 1], 'description' => 'Step 1']
                ]
            ]),
            // 2. Reflector response (continue)
            json_encode([
                'decision' => 'continue',
                'thought' => 'Need more data.',
                'next_suggestion' => 'Try test_tool again with more details'
            ]),
            // 3. Reflector response (finish)
            json_encode([
                'decision' => 'finish',
                'thought' => 'Now it is enough.',
                'next_suggestion' => null
            ])
        ])->preventStrayPrompts();

        $planner = new Planner($toolRegistry);
        $reflector = new Reflector();
        $controller = new LoopController($planner, $reflector, $toolRegistry, 3);

        $result = $controller->execute("Test multi-step");

        $this->assertStringContainsString('Финальный анализ: Now it is enough.', $result);
        $this->assertStringContainsString('1. [test_tool] Step 1', $result);
        $this->assertStringContainsString('2. [test_tool] Try test_tool again with more details', $result);
    }
}
