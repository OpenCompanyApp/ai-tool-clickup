<?php

namespace OpenCompany\AiToolClickUp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolClickUp\ClickUpService;

class ClickUpUpdateTask implements Tool
{
    public function __construct(
        private ClickUpService $service,
    ) {}

    public function description(): string
    {
        return <<<'MD'
        Update an existing ClickUp task.
        Supports changing name, description, status, priority, assignees, and dates.
        Set status to "closed" to complete a task.
        Supports custom task IDs like "DEV-42".
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

            $data = [];

            if (isset($request['name'])) {
                $data['name'] = $request['name'];
            }
            if (isset($request['description'])) {
                $data['description'] = $request['description'];
            }
            if (isset($request['status'])) {
                $data['status'] = $request['status'];
            }
            if (isset($request['priority'])) {
                $data['priority'] = (int) $request['priority'];
            }
            if (isset($request['assignees'])) {
                $assignees = is_string($request['assignees']) ? explode(',', $request['assignees']) : $request['assignees'];
                $data['assignees'] = ['add' => array_map('intval', array_map('trim', $assignees))];
            }
            if (isset($request['removeAssignees'])) {
                $remove = is_string($request['removeAssignees']) ? explode(',', $request['removeAssignees']) : $request['removeAssignees'];
                $data['assignees'] = array_merge($data['assignees'] ?? [], [
                    'rem' => array_map('intval', array_map('trim', $remove)),
                ]);
            }
            if (isset($request['dueDate'])) {
                if ($request['dueDate'] === '') {
                    $data['due_date'] = null;
                } else {
                    $data['due_date'] = ClickUpService::toMillis($request['dueDate']);
                    $data['due_date_time'] = true;
                }
            }
            if (isset($request['startDate'])) {
                if ($request['startDate'] === '') {
                    $data['start_date'] = null;
                } else {
                    $data['start_date'] = ClickUpService::toMillis($request['startDate']);
                    $data['start_date_time'] = true;
                }
            }
            if (isset($request['timeEstimate'])) {
                $data['time_estimate'] = (int) $request['timeEstimate'] * 60000; // minutes to ms
            }

            if (empty($data)) {
                return 'Error: At least one field to update is required.';
            }

            // Handle custom task IDs
            $queryParams = $this->service->withCustomIdParams($taskId);
            if (! empty($queryParams)) {
                // Append query params to the task ID URL
                $taskId .= '?' . http_build_query($queryParams);
            }

            $result = $this->service->updateTask($taskId, $data);

            return "Task updated successfully.\n"
                . json_encode([
                    'id' => $result['id'] ?? '',
                    'name' => $result['name'] ?? '',
                    'status' => $result['status']['status'] ?? '',
                    'url' => $result['url'] ?? '',
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            return "Error updating task: {$e->getMessage()}";
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
                ->description('Task ID to update. Supports custom IDs like "DEV-42".')
                ->required(),
            'name' => $schema
                ->string()
                ->description('New task name.'),
            'description' => $schema
                ->string()
                ->description('New task description.'),
            'status' => $schema
                ->string()
                ->description('New status. Set to "closed" to complete the task.'),
            'priority' => $schema
                ->integer()
                ->description('Priority: 1=urgent, 2=high, 3=normal, 4=low.'),
            'assignees' => $schema
                ->string()
                ->description('Comma-separated user IDs to add as assignees.'),
            'removeAssignees' => $schema
                ->string()
                ->description('Comma-separated user IDs to remove as assignees.'),
            'dueDate' => $schema
                ->string()
                ->description('New due date (ISO 8601). Empty string to clear.'),
            'startDate' => $schema
                ->string()
                ->description('New start date (ISO 8601). Empty string to clear.'),
            'timeEstimate' => $schema
                ->integer()
                ->description('Time estimate in minutes.'),
        ];
    }
}
