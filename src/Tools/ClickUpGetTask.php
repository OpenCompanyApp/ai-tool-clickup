<?php

namespace OpenCompany\AiToolClickUp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolClickUp\ClickUpService;

class ClickUpGetTask implements Tool
{
    public function __construct(
        private ClickUpService $service,
    ) {}

    public function description(): string
    {
        return <<<'MD'
        Get a single ClickUp task by ID with full details.
        Supports both regular IDs and custom IDs (e.g., "DEV-42").
        Optionally include subtask details.
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

            $params = $this->service->withCustomIdParams($taskId);

            if (isset($request['includeSubtasks']) && $request['includeSubtasks']) {
                $params['include_subtasks'] = 'true';
            }

            $task = $this->service->getTask($taskId, $params);

            $output = [
                'id' => $task['id'] ?? '',
                'custom_id' => $task['custom_id'] ?? null,
                'name' => $task['name'] ?? '',
                'description' => $task['description'] ?? '',
                'status' => $task['status']['status'] ?? '',
                'priority' => $task['priority']['priority'] ?? null,
                'assignees' => array_map(fn (array $a) => [
                    'id' => $a['id'] ?? '',
                    'username' => $a['username'] ?? '',
                ], $task['assignees'] ?? []),
                'tags' => array_map(fn (array $t) => $t['name'] ?? '', $task['tags'] ?? []),
                'due_date' => isset($task['due_date']) ? ClickUpService::fromMillis((int) $task['due_date']) : null,
                'start_date' => isset($task['start_date']) ? ClickUpService::fromMillis((int) $task['start_date']) : null,
                'time_estimate' => $task['time_estimate'] ?? null,
                'url' => $task['url'] ?? '',
                'list' => [
                    'id' => $task['list']['id'] ?? '',
                    'name' => $task['list']['name'] ?? '',
                ],
                'folder' => [
                    'id' => $task['folder']['id'] ?? '',
                    'name' => $task['folder']['name'] ?? '',
                ],
                'space' => [
                    'id' => $task['space']['id'] ?? '',
                ],
            ];

            if (! empty($task['subtasks'])) {
                $output['subtasks'] = array_map(fn (array $st) => [
                    'id' => $st['id'] ?? '',
                    'name' => $st['name'] ?? '',
                    'status' => $st['status']['status'] ?? '',
                ], $task['subtasks']);
            }

            return json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            return "Error getting task: {$e->getMessage()}";
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
                ->description('Task ID. Supports regular IDs or custom IDs like "DEV-42".')
                ->required(),
            'includeSubtasks' => $schema
                ->boolean()
                ->description('Include subtask details. Default: false.'),
        ];
    }
}
