<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use App\AI\Events\UserMessageReceived;
use App\AI\Events\PlanCreated;
use App\AI\Events\ToolCalled;
use App\AI\Events\ToolResultReceived;
use App\AI\Events\ReflectionGenerated;
use App\AI\Listeners\PlanListener;
use App\AI\Listeners\ExecuteToolListener;
use App\AI\Listeners\ReflectListener;
use App\AI\Listeners\LoopListener;
use App\AI\Listeners\ProcessPlanListener;
use App\Console\Commands\TestAiAgent;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
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
