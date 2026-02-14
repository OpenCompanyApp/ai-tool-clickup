<?php

namespace OpenCompany\AiToolClickUp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolClickUp\ClickUpService;

class ClickUpCreateTask implements Tool
{
    public function __construct(
        private ClickUpService $service,
    ) {}

    public function description(): string
    {
        return <<<'MD'
        Create a new task in a ClickUp list.
        Requires a list ID and task name. Supports description, status,
        priority, assignees, dates, tags, and creating subtasks via parentTaskId.
        Use clickup_get_hierarchy to find list IDs.
        MD;
    }

    public function handle(Request $request): string
    {
        try {
            if (! $this->service->isConfigured()) {
                return 'Error: ClickUp integration is not configured.';
            }

            $listId = $request['listId'] ?? '';
            $name = $request['name'] ?? '';

            if (empty($listId)) {
                return 'Error: listId is required.';
            }
            if (empty($name)) {
                return 'Error: name is required.';
            }

            $data = ['name' => $name];

            if (isset($request['description'])) {
                $data['description'] = $request['description'];
            }
            if (isset($request['status'])) {
                $data['status'] = $request['status'];
            }
            if (isset($request['priority'])) {
                // ClickUp priority: 1=urgent, 2=high, 3=normal, 4=low
                $data['priority'] = (int) $request['priority'];
            }
            if (isset($request['assignees'])) {
                $assignees = is_string($request['assignees']) ? explode(',', $request['assignees']) : $request['assignees'];
                $data['assignees'] = array_map('intval', array_map('trim', $assignees));
            }
            if (isset($request['dueDate'])) {
                $data['due_date'] = ClickUpService::toMillis($request['dueDate']);
                $data['due_date_time'] = true;
            }
            if (isset($request['startDate'])) {
                $data['start_date'] = ClickUpService::toMillis($request['startDate']);
                $data['start_date_time'] = true;
            }
            if (isset($request['tags'])) {
                $tags = is_string($request['tags']) ? explode(',', $request['tags']) : $request['tags'];
                $data['tags'] = array_map('trim', $tags);
            }
            if (isset($request['parentTaskId'])) {
                $data['parent'] = $request['parentTaskId'];
            }

            $result = $this->service->createTask($listId, $data);

            return "Task '{$name}' created successfully (ID: {$result['id']}).\n"
                . json_encode([
                    'id' => $result['id'],
                    'name' => $result['name'],
                    'status' => $result['status']['status'] ?? '',
                    'url' => $result['url'] ?? '',
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            return "Error creating task: {$e->getMessage()}";
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
                ->description('List ID to create the task in.')
                ->required(),
            'name' => $schema
                ->string()
                ->description('Task name.')
                ->required(),
            'description' => $schema
                ->string()
                ->description('Task description text.'),
            'status' => $schema
                ->string()
                ->description('Task status (must be valid for the list).'),
            'priority' => $schema
                ->integer()
                ->description('Priority: 1=urgent, 2=high, 3=normal, 4=low.'),
            'assignees' => $schema
                ->string()
                ->description('Comma-separated user IDs to assign. Use clickup_members to resolve names to IDs.'),
            'dueDate' => $schema
                ->string()
                ->description('Due date in ISO 8601 format (e.g., "2026-03-15" or "2026-03-15T14:30:00").'),
            'startDate' => $schema
                ->string()
                ->description('Start date in ISO 8601 format.'),
            'tags' => $schema
                ->string()
                ->description('Comma-separated tag names. Tags must exist in the space.'),
            'parentTaskId' => $schema
                ->string()
                ->description('Parent task ID to create this as a subtask.'),
        ];
    }
}
