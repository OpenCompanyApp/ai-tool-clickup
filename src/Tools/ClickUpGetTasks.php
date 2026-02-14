<?php

namespace OpenCompany\AiToolClickUp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolClickUp\ClickUpService;

class ClickUpGetTasks implements Tool
{
    public function __construct(
        private ClickUpService $service,
    ) {}

    public function description(): string
    {
        return <<<'MD'
        Get all tasks in a ClickUp list.
        Supports filtering by statuses, assignees, and due dates.
        Use clickup_get_hierarchy first to find the list ID.
        MD;
    }

    public function handle(Request $request): string
    {
        try {
            if (! $this->service->isConfigured()) {
                return 'Error: ClickUp integration is not configured.';
            }

            $listId = $request['listId'] ?? '';
            if (empty($listId)) {
                return 'Error: listId is required.';
            }

            $params = [];

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

            if (isset($request['dueDateGt'])) {
                $params['due_date_gt'] = ClickUpService::toMillis($request['dueDateGt']);
            }

            if (isset($request['dueDateLt'])) {
                $params['due_date_lt'] = ClickUpService::toMillis($request['dueDateLt']);
            }

            if (isset($request['includeClosed']) && $request['includeClosed']) {
                $params['include_closed'] = 'true';
            }

            if (isset($request['page'])) {
                $params['page'] = (int) $request['page'];
            }

            $result = $this->service->getTasks($listId, $params);
            $tasks = $result['tasks'] ?? [];

            if (empty($tasks)) {
                return 'No tasks found in this list.';
            }

            $output = array_map(fn (array $task) => [
                'id' => $task['id'] ?? '',
                'custom_id' => $task['custom_id'] ?? null,
                'name' => $task['name'] ?? '',
                'status' => $task['status']['status'] ?? '',
                'priority' => $task['priority']['priority'] ?? null,
                'assignees' => array_map(fn (array $a) => $a['username'] ?? $a['id'], $task['assignees'] ?? []),
                'due_date' => isset($task['due_date']) ? ClickUpService::fromMillis((int) $task['due_date']) : null,
            ], $tasks);

            return json_encode(['count' => count($output), 'tasks' => $output], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            return "Error getting tasks: {$e->getMessage()}";
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'listId' => $schema
                ->string()
                ->description('List ID to get tasks from.')
                ->required(),
            'statuses' => $schema
                ->string()
                ->description('Comma-separated statuses to filter by.'),
            'assignees' => $schema
                ->string()
                ->description('Comma-separated assignee user IDs.'),
            'dueDateGt' => $schema
                ->string()
                ->description('Only tasks with due date after this (ISO 8601, e.g., "2026-01-01").'),
            'dueDateLt' => $schema
                ->string()
                ->description('Only tasks with due date before this (ISO 8601).'),
            'includeClosed' => $schema
                ->boolean()
                ->description('Include closed tasks. Default: false.'),
            'page' => $schema
                ->integer()
                ->description('Page number for pagination (starts at 0).'),
        ];
    }
}
