<?php

namespace OpenCompany\AiToolClickUp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolClickUp\ClickUpService;

class ClickUpLogTime implements Tool
{
    public function __construct(
        private ClickUpService $service,
    ) {}

    public function description(): string
    {
        return 'Add a manual time entry to a ClickUp task.';
    }

    public function handle(Request $request): string
    {
        try {
            if (! $this->service->isConfigured()) {
                return 'Error: ClickUp integration is not configured.';
            }

            $workspaceId = $request['workspaceId'] ?? $this->service->getWorkspaceId();
            if (empty($workspaceId)) {
                return 'Error: Workspace ID is required. Configure it in settings or pass workspaceId.';
            }

            $taskId = $request['taskId'] ?? '';
            $start = $request['start'] ?? '';
            $duration = $request['duration'] ?? '';

            if (empty($taskId)) {
                return 'Error: taskId is required.';
            }
            if (empty($start)) {
                return 'Error: start time is required (ISO 8601).';
            }
            if (empty($duration)) {
                return 'Error: duration is required in milliseconds.';
            }

            $data = [
                'tid' => $taskId,
                'start' => ClickUpService::toMillis($start),
                'duration' => (int) $duration,
            ];

            if (isset($request['description'])) {
                $data['description'] = $request['description'];
            }
            if (isset($request['billable'])) {
                $data['billable'] = (bool) $request['billable'];
            }
            if (isset($request['tags'])) {
                $tags = is_string($request['tags']) ? explode(',', $request['tags']) : $request['tags'];
                $data['tags'] = array_map(fn (string $t) => ['name' => trim($t)], $tags);
            }

            $result = $this->service->addTimeEntry($workspaceId, $data);

            return "Time entry logged.\n"
                . json_encode($result['data'] ?? $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            return "Error logging time: {$e->getMessage()}";
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
                ->description('Task ID.')
                ->required(),
            'start' => $schema
                ->string()
                ->description('Start time in ISO 8601 format.')
                ->required(),
            'duration' => $schema
                ->string()
                ->description('Duration in milliseconds.')
                ->required(),
            'description' => $schema
                ->string()
                ->description('Description for the time entry.'),
            'billable' => $schema
                ->boolean()
                ->description('Whether the time is billable.'),
            'tags' => $schema
                ->string()
                ->description('Comma-separated tag names for the time entry.'),
            'workspaceId' => $schema
                ->string()
                ->description('Workspace ID. Uses configured default if omitted.'),
        ];
    }
}
