<?php

namespace App\Providers;

use App\Ai\Core\Supervisor;
use App\Ai\Agents\SummaryAgent;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use App\Ai\Events\UserMessageReceived;
use App\Ai\Events\PlanCreated;
use App\Ai\Events\ToolCalled;
use App\Ai\Events\ToolResultReceived;
use App\Ai\Events\ReflectionGenerated;
use App\Ai\Listeners\PlanListener;
use App\Ai\Listeners\ExecuteToolListener;
use App\Ai\Listeners\ReflectListener;
use App\Ai\Listeners\LoopListener;
use App\Ai\Listeners\ProcessPlanListener;
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

        $this->app->singleton(Supervisor::class, function ($app) {
            return new Supervisor(
                $app->make(ResearchAgent::class),
                $app->make(SummaryAgent::class)
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
        Event::listen(UserMessageReceived::class, PlanListener::class);
        Event::listen(PlanCreated::class, ProcessPlanListener::class);
        Event::listen(ToolCalled::class, ExecuteToolListener::class);
        Event::listen(ToolResultReceived::class, ReflectListener::class);
        Event::listen(ReflectionGenerated::class, LoopListener::class);

        $this->commands([
            TestAiAgent::class,
        ]);
    }
}
