<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Ai\Tools\ToolRegistry;
use App\Ai\Tools\CalculatorTool;
use Laravel\Ai\Tools\Request;

class ToolRegistryTest extends TestCase
{
    public function test_can_register_and_get_tool()
    {
        $registry = new ToolRegistry();
        $tool = new CalculatorTool();

        $registry->register('calculator', $tool);

        $this->assertTrue($registry->has('calculator'));
        $this->assertSame($tool, $registry->get('calculator'));
        $this->assertCount(1, $registry->all());
    }

    public function test_returns_null_for_unknown_tool()
    {
        $registry = new ToolRegistry();
        $this->assertNull($registry->get('non_existent'));
        $this->assertFalse($registry->has('non_existent'));
    }

    public function test_can_get_tools_definitions()
    {
        $registry = new ToolRegistry();
        $registry->register('calculator', new CalculatorTool());

        $definitions = $registry->getToolsDefinitions();

        $this->assertArrayHasKey('calculator', $definitions);
        $this->assertEquals('calculator', $definitions['calculator']['name']);
        $this->assertEquals('Calculates a mathematical expression.', $definitions['calculator']['description']);
        $this->assertIsArray($definitions['calculator']['parameters']);
        $this->assertEquals('object', $definitions['calculator']['parameters']['type']);
        $this->assertArrayHasKey('expression', $definitions['calculator']['parameters']['properties']);
        $this->assertContains('expression', $definitions['calculator']['parameters']['required']);
    }

    public function test_can_get_vector_search_tool_definition()
    {
        $registry = new ToolRegistry();
        $mockStore = $this->createMock(\App\Ai\Memory\VectorStore::class);
        $registry->register('vector_search', new \App\Ai\Tools\VectorSearchTool($mockStore));

        $definitions = $registry->getToolsDefinitions();

        $this->assertArrayHasKey('vector_search', $definitions);
        $this->assertEquals('Searches the knowledge base for relevant information about a specific query.', $definitions['vector_search']['description']);

        $params = $definitions['vector_search']['parameters'];
        $this->assertArrayHasKey('query', $params['properties']);
        $this->assertArrayHasKey('limit', $params['properties']);

        $this->assertContains('query', $params['required']);
        $this->assertNotContains('limit', $params['required']);
    }

    public function test_calculator_tool_execution()
    {
        $tool = new CalculatorTool();

        $request = new Request(['expression' => '2 + 2 * 2']);
        $result = $tool->handle($request);
        $this->assertEquals('6', $result);

        $request = new Request(['expression' => '(10 + 5) / 3']);
        $result = $tool->handle($request);
        $this->assertEquals('5', $result);
    }
}
