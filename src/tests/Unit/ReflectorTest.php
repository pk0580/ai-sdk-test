<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Ai\Core\Reflector;
use App\Ai\Tools\ToolRegistry;
use App\Ai\DTO\Step;
use Mockery;
use App\Ai\Agents\CheapAnonymousAgent;
use Laravel\Ai\Ai;
use Laravel\Ai\Providers\Fakes\ProviderFake;

class ReflectorTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_reflector_returns_correct_decision()
    {
        $jsonResponse = json_encode([
            'decision' => 'finish',
            'thought' => 'The calculation is complete.',
            'next_suggestion' => null
        ]);

        CheapAnonymousAgent::fake([
            $jsonResponse
        ]);

        $registry = Mockery::mock(ToolRegistry::class)->makePartial();
        $registry->shouldReceive('getToolsDefinitions')->andReturn([]);

        $reflector = new Reflector($registry);
        $step = new Step('calculator', ['expression' => '25 * 17'], 'Calc');
        $batchResults = [
            ['step' => $step, 'result' => 425]
        ];

        $analysis = $reflector->reflect('What is 25 * 17?', $batchResults);

        $this->assertEquals('finish', $analysis['decision']);
        $this->assertEquals('The calculation is complete.', $analysis['thought']);
        $this->assertNull($analysis['next_suggestion']);
    }

    public function test_reflector_handles_invalid_json()
    {
        CheapAnonymousAgent::fake([
            'Invalid JSON'
        ]);

        $registry = Mockery::mock(ToolRegistry::class)->makePartial();
        $registry->shouldReceive('getToolsDefinitions')->andReturn([]);

        $reflector = new Reflector($registry);
        $step = new Step('test', [], 'test');
        $batchResults = [
            ['step' => $step, 'result' => 'result']
        ];

        $analysis = $reflector->reflect('test', $batchResults);

        $this->assertEquals('finish', $analysis['decision']);
        $this->assertStringContainsString('Не удалось распарсить', $analysis['thought']);
    }

    public function test_reflector_analyzes_entire_batch()
    {
        CheapAnonymousAgent::fake([
            json_encode([
                'decision' => 'finish',
                'thought' => 'All steps processed.',
                'next_suggestion' => null
            ])
        ]);

        $registry = Mockery::mock(ToolRegistry::class)->makePartial();
        $registry->shouldReceive('getToolsDefinitions')->andReturn([]);

        $reflector = new Reflector($registry);
        $batchResults = [
            ['step' => new Step('tool1', [], 'Step 1'), 'result' => 'Result 1'],
            ['step' => new Step('tool2', [], 'Step 2'), 'result' => 'Result 2']
        ];

        $reflector->reflect('Multi-step query', $batchResults);

        CheapAnonymousAgent::assertPrompted(function ($agentPrompt) {
            return str_contains($agentPrompt->prompt, 'Result 1') && str_contains($agentPrompt->prompt, 'Result 2');
        });
    }
}
