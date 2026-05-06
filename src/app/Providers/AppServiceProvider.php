<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Ai\Conversation\Event\StepCompleted;
use App\Application\Ai\Conversation\Event\StepPlanned;
use App\Application\Ai\Conversation\Event\StepRequested;
use App\Application\Ai\Conversation\Listener\ExecuteStepListener;
use App\Application\Ai\Conversation\Listener\PlanNextStepListener;
use App\Application\Ai\Conversation\Listener\UpdateStateListener;
use App\Domain\Ai\Conversation\AgentExecutorInterface;
use App\Domain\Ai\Conversation\ConversationCancellationInterface;
use App\Domain\Ai\Conversation\PlannerInterface;
use App\Domain\Ai\Knowledge\DocumentRepositoryInterface;
use App\Domain\Ai\Knowledge\EmbeddingProviderInterface;
use App\Domain\Ai\Knowledge\QueryRewriterInterface;
use App\Domain\Ai\Knowledge\RerankerInterface;
use App\Domain\Ai\Logging\AiLogRepositoryInterface;
use App\Domain\Ai\Tooling\ToolPlannerInterface;
use App\Domain\Ai\Tooling\ToolRegistryInterface;
use App\Infrastructure\Ai\Cancellation\CacheConversationCancellation;
use App\Infrastructure\Ai\Executor\AgentRegistryExecutor;
use App\Infrastructure\Ai\Memory\LaravelAiEmbeddingProvider;
use App\Infrastructure\Ai\Memory\PgVectorDocumentRepository;
use App\Infrastructure\Ai\Planner\LlmDynamicPlanner;
use App\Infrastructure\Ai\Planner\LlmToolsPlanner;
use App\Infrastructure\Ai\QueryRewriter\LlmQueryRewriter;
use App\Infrastructure\Ai\Reranker\LlmReranker;
use App\Infrastructure\Ai\Tool\CalculatorTool;
use App\Infrastructure\Ai\Tool\InMemoryToolRegistry;
use App\Infrastructure\Ai\Tool\VectorSearchTool;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentAiLogRepository;
use App\Interface\Console\Commands\IndexDocumentsCommand;
use App\Interface\Console\Commands\TestAiAgentCommand;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(InMemoryToolRegistry::class, function ($app) {
            $registry = new InMemoryToolRegistry();
            $registry->register('calculator', new CalculatorTool());
            $registry->register('vector_search', $app->make(VectorSearchTool::class));

            return $registry;
        });
        $this->app->bind(ToolRegistryInterface::class, InMemoryToolRegistry::class);

        $this->app->bind(EmbeddingProviderInterface::class, LaravelAiEmbeddingProvider::class);
        $this->app->bind(DocumentRepositoryInterface::class, PgVectorDocumentRepository::class);
        $this->app->bind(QueryRewriterInterface::class, LlmQueryRewriter::class);
        $this->app->bind(RerankerInterface::class, LlmReranker::class);

        $this->app->bind(PlannerInterface::class, LlmDynamicPlanner::class);
        $this->app->bind(ToolPlannerInterface::class, LlmToolsPlanner::class);

        $this->app->singleton(AgentRegistryExecutor::class);
        $this->app->bind(AgentExecutorInterface::class, AgentRegistryExecutor::class);

        $this->app->bind(ConversationCancellationInterface::class, CacheConversationCancellation::class);
        $this->app->bind(AiLogRepositoryInterface::class, EloquentAiLogRepository::class);
    }

    public function boot(): void
    {
        $this->commands([
            TestAiAgentCommand::class,
            IndexDocumentsCommand::class,
        ]);

        Event::listen(StepRequested::class, [PlanNextStepListener::class, 'handle']);
        Event::listen(StepPlanned::class, [ExecuteStepListener::class, 'handle']);
        Event::listen(StepCompleted::class, [UpdateStateListener::class, 'handle']);
    }
}
