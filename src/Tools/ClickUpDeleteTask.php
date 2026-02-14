<?php

namespace OpenCompany\AiToolClickUp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolClickUp\ClickUpService;

class ClickUpDeleteTask implements Tool
{
    public function __construct(
        private ClickUpService $service,
    ) {}

    public function description(): string
    {
        return <<<'MD'
        Delete a ClickUp task permanently.
        Supports custom task IDs like "DEV-42".
        This action cannot be undone.
        MD;
    }

    public function handle(Request $request): string
    {
        try {
            if (! $this->service->isConfigured()) {
                return 'Error: ClickUp integration is not configured.';
            }

            $taskId = $request['taskId'] ?? '';
            if (empty($taskId)) {
                return 'Error: taskId is required.';
            }

            $queryParams = $this->service->withCustomIdParams($taskId);
            $deleteId = $taskId;
            if (! empty($queryParams)) {
                $deleteId .= '?' . http_build_query($queryParams);
            }

            $this->service->deleteTask($deleteId);

            return "Task '{$taskId}' deleted successfully.";
        } catch (\Throwable $e) {
            return "Error deleting task: {$e->getMessage()}";
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'taskId' => $schema
                ->string()
                ->description('Task ID to delete. Supports custom IDs like "DEV-42".')
                ->required(),
        ];
    }
}
