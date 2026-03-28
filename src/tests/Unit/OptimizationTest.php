<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Ai\Agents\CheapAnonymousAgent;
use App\Ai\Agents\SmartAnonymousAgent;
use App\Ai\Core\Planner;
use App\Ai\Tools\ToolRegistry;
use App\Ai\Core\LoopController;
use App\Ai\Core\Reflector;
use App\Ai\DTO\Step;
use Illuminate\Support\Facades\Cache;
use Laravel\Ai\AnonymousAgent;
use Mockery;

class OptimizationTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_planner_uses_cache()
    {
        Cache::flush();
        $toolRegistry = app(ToolRegistry::class);
        $planner = new Planner($toolRegistry);

        // Фейкаем LLM для Planner (CheapAnonymousAgent)
        CheapAnonymousAgent::fake([
            ['steps' => [['tool' => 'calculator', 'parameters' => ['expression' => '2+2'], 'description' => 'Test OrchestrationStep']]]
        ]);

        // Первый вызов - должен пойти в LLM
        $plan1 = $planner->generate("Calculate 2+2");
        $this->assertCount(1, $plan1->steps);
        $this->assertEquals('calculator', $plan1->steps[0]->tool);

        // Второй вызов с тем же сообщением - должен взять из кеша (LLM не должна вызываться, если бы мы не фейкали, но тут мы проверяем факт кеша)
        // В AnonymousAgent::fake, если мы не предоставим второй ответ, и вызов будет, то упадет ошибка или вернется дефолт.
        // Но мы можем проверить наличие в кеше.
        $cacheKey = 'ai_plan_' . md5("Calculate 2+2" . json_encode($toolRegistry->getToolsDefinitions()));
        $this->assertTrue(Cache::has($cacheKey));

        $cachedData = Cache::get($cacheKey);
        $this->assertEquals('calculator', $cachedData['steps'][0]['tool']);
    }

    public function test_loop_controller_batches_steps()
    {
        $planner = Mockery::mock(Planner::class);
        $reflector = Mockery::mock(Reflector::class);
        $toolRegistry = app(ToolRegistry::class);

        $loopController = new LoopController($planner, $reflector, $toolRegistry);

        $steps = [
            new Step('calculator', ['expression' => '1+1'], 'OrchestrationStep 1'),
            new Step('calculator', ['expression' => '2+2'], 'OrchestrationStep 2'),
            new Step('calculator', ['expression' => '3+3'], 'OrchestrationStep 3'),
            new Step('calculator', ['expression' => '4+4'], 'OrchestrationStep 4'),
        ];

        $planner->shouldReceive('generate')->once()->andReturn(new \App\Ai\DTO\Plan($steps));

        // Рефлексия должна быть вызвана после первого батча (3 шага) и после второго (1 шаг)
        // Первый батч: шаги 1, 2, 3. Рефлектор получает 3-й шаг.
        $reflector->shouldReceive('reflect')
            ->once()
            ->with("Batch task", Mockery::on(fn($s) => $s->parameters['expression'] === '3+3'), Mockery::any())
            ->andReturn(['decision' => 'continue', 'thought' => 'More steps needed']);

        // Второй батч: шаг 4. Рефлектор получает 4-й шаг.
        $reflector->shouldReceive('reflect')
            ->once()
            ->with("Batch task", Mockery::on(fn($s) => $s->parameters['expression'] === '4+4'), Mockery::any())
            ->andReturn(['decision' => 'finish', 'thought' => 'All done']);

        // Фейкаем финальный ответ (SmartAnonymousAgent использует smartest модель)
        SmartAnonymousAgent::fake(['Final batch response']);

        $response = $loopController->execute("Batch task");

        $this->assertEquals('Final batch response', $response);
    }
}
