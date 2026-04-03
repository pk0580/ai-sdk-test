<?php

namespace App\Providers;

use App\Ai\Core\AgentRegistry;
use App\Ai\Core\DynamicPlanner;
use App\Ai\Core\Interfaces\DynamicPlannerInterface;
use App\Ai\Core\Supervisor;
use App\Ai\Agents\SummaryAgent;
use App\Ai\Agents\ResearchAgent;
use App\Ai\Tools\CalculatorTool;
use App\Ai\Tools\VectorSearchTool;
use App\Ai\Tools\ToolRegistry;
use App\Ai\Events\Workflow\StepRequested;
use App\Ai\Events\Workflow\StepPlanned;
use App\Ai\Events\Workflow\StepCompleted;
use App\Ai\Listeners\Workflow\PlanNextStepListener;
use App\Ai\Listeners\Workflow\ExecuteStepListener;
use App\Ai\Listeners\Workflow\UpdateStateListener;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use App\Console\Commands\TestAiAgent;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ToolRegistry::class, function () {
            $registry = new ToolRegistry();
            $registry->register('calculator', new CalculatorTool());
            $registry->register('vector_search', $this->app->make(VectorSearchTool::class));

            return $registry;
        });

        $this->app->bind(DynamicPlannerInterface::class, DynamicPlanner::class);

        $this->app->singleton(AgentRegistry::class, function ($app) {
            return new AgentRegistry(
                $app->make(ResearchAgent::class),
                $app->make(SummaryAgent::class)
            );
        });

        $this->app->singleton(Supervisor::class);

        $this->app->bind(ResearchAgent::class, function ($app) {
            return new ResearchAgent(
                $app->make(ToolRegistry::class)
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->commands([
            TestAiAgent::class,
        ]);

        Event::listen(StepRequested::class, PlanNextStepListener::class);
        Event::listen(StepPlanned::class, ExecuteStepListener::class);
        Event::listen(StepCompleted::class, UpdateStateListener::class);
    }
}
