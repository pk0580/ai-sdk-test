<?php

namespace App\Providers;

use App\Ai\Core\Supervisor;
use App\Ai\Core\HybridOrchestratorPlanner;
use App\Ai\Core\Interfaces\OrchestratorPlannerInterface;
use App\Ai\Agents\SummaryAgent;
use Illuminate\Support\ServiceProvider;
use App\Console\Commands\TestAiAgent;
use App\Ai\Core\LoopController;
use App\Ai\Agents\ResearchAgent;
use App\Ai\Tools\CalculatorTool;
use App\Ai\Tools\VectorSearchTool;
use App\Ai\Tools\ToolRegistry;

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

        $this->app->bind(OrchestratorPlannerInterface::class, HybridOrchestratorPlanner::class);

        $this->app->singleton(Supervisor::class, function ($app) {
            return new Supervisor(
                $app->make(ResearchAgent::class),
                $app->make(SummaryAgent::class),
                $app->make(OrchestratorPlannerInterface::class)
            );
        });

        $this->app->bind(ResearchAgent::class, function ($app) {
            return new ResearchAgent(
                $app->make(LoopController::class),
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
    }
}
