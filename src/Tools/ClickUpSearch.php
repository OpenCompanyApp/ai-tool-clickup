<?php

namespace OpenCompany\AiToolClickUp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolClickUp\ClickUpService;

class ClickUpSearch implements Tool
{
    public function __construct(
        private ClickUpService $service,
    ) {}

    public function description(): string
    {
        return <<<'MD'
        Search tasks across the ClickUp workspace.
        Supports filtering by query, statuses, assignees, and more.
        Returns matching tasks with their details.
        MD;
    }

    public function handle(Request $request): string
    {
        try {
            if (! $this->service->isConfigured()) {
                return 'Error: ClickUp integration is not configured.';
            }

            $workspaceId = $request['workspaceId'] ?? $this->service->getWorkspaceId();
            if (empty($workspaceId)) {
                return 'Error: Workspace ID is required for search. Configure it in settings or pass workspaceId.';
            }

            $params = [];

            if (isset($request['query'])) {
                // The ClickUp search API doesn't have a direct "query" param for v2 task search.
                // We use the filtered task list endpoint instead.
                // For name-based search, we'll filter client-side or use the search endpoint.
                $params['name'] = $request['query'];
            }

            if (isset($request['statuses'])) {
                $statuses = is_string($request['statuses']) ? explode(',', $request['statuses']) : $request['statuses'];
                foreach ($statuses as $i => $status) {
                    $params["statuses[{$i}]"] = trim($status);
                }
            }

            if (isset($request['assignees'])) {
                $assignees = is_string($request['assignees']) ? explode(',', $request['assignees']) : $request['assignees'];
                foreach ($assignees as $i => $assignee) {
                    $params["assignees[{$i}]"] = trim($assignee);
                }
            }

            if (isset($request['includeClosed']) && $request['includeClosed']) {
                $params['include_closed'] = 'true';
            }

            if (isset($request['includeSubtasks']) && $request['includeSubtasks']) {
                $params['subtasks'] = 'true';
            }

            if (isset($request['page'])) {
                $params['page'] = (int) $request['page'];
            }

            $result = $this->service->searchTasks($workspaceId, $params);
            $tasks = $result['tasks'] ?? [];

            if (empty($tasks)) {
                return 'No tasks found matching the search criteria.';
            }

            $output = [];
            foreach ($tasks as $task) {
                $output[] = $this->formatTask($task);
            }

            return json_encode(['count' => count($output), 'tasks' => $output], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            return "Error searching tasks: {$e->getMessage()}";
        }
    }

    /**
     * @param  array<string, mixed>  $task
     * @return array<string, mixed>
     */
    private function formatTask(array $task): array
    {
        return [
            'id' => $task['id'] ?? '',
            'custom_id' => $task['custom_id'] ?? null,
            'name' => $task['name'] ?? '',
            'status' => $task['status']['status'] ?? '',
            'priority' => $task['priority']['priority'] ?? null,
            'assignees' => array_map(fn (array $a) => $a['username'] ?? $a['email'] ?? $a['id'], $task['assignees'] ?? []),
            'due_date' => isset($task['due_date']) ? ClickUpService::fromMillis((int) $task['due_date']) : null,
            'url' => $task['url'] ?? '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema
                ->string()
                ->description('Search query to match task names.'),
            'statuses' => $schema
                ->string()
                ->description('Comma-separated statuses to filter by (e.g., "open,in progress").'),
            'assignees' => $schema
                ->string()
                ->description('Comma-separated assignee user IDs.'),
            'includeClosed' => $schema
                ->boolean()
                ->description('Include closed tasks in results. Default: false.'),
            'includeSubtasks' => $schema
                ->boolean()
                ->description('Include subtasks in results. Default: false.'),
            'page' => $schema
                ->integer()
                ->description('Page number for pagination (starts at 0).'),
            'workspaceId' => $schema
                ->string()
                ->description('Workspace ID. Uses configured default if omitted.'),
        ];
    }
}
