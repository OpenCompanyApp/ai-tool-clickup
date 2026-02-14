<?php

namespace OpenCompany\AiToolClickUp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolClickUp\ClickUpService;

class ClickUpTimeTracking implements Tool
{
    public function __construct(
        private ClickUpService $service,
    ) {}

    public function description(): string
    {
        return <<<'MD'
        Manage time tracking on ClickUp tasks. Actions:
        - **start**: Start a timer on a task.
        - **stop**: Stop the currently running timer.
        - **log**: Add a manual time entry.
        - **list**: Get all time entries for a task.
        - **current**: Get the currently running time entry.
        MD;
    }

    public function handle(Request $request): string
    {
        try {
            if (! $this->service->isConfigured()) {
                return 'Error: ClickUp integration is not configured.';
            }

            $action = $request['action'] ?? '';
            if (empty($action)) {
                return 'Error: action is required (start, stop, log, list, current).';
            }

            $workspaceId = $request['workspaceId'] ?? $this->service->getWorkspaceId();
            if (empty($workspaceId) && $action !== 'list') {
                return 'Error: Workspace ID is required for time tracking. Configure it in settings or pass workspaceId.';
            }

            return match ($action) {
                'start' => $this->startTimer($workspaceId, $request),
                'stop' => $this->stopTimer($workspaceId),
                'log' => $this->logTime($workspaceId, $request),
                'list' => $this->listEntries($request),
                'current' => $this->currentEntry($workspaceId),
                default => "Error: Unknown action '{$action}'. Use: start, stop, log, list, current.",
            };
        } catch (\Throwable $e) {
            return "Error with time tracking: {$e->getMessage()}";
        }
    }

    private function startTimer(string $workspaceId, Request $request): string
    {
        $taskId = $request['taskId'] ?? '';
        if (empty($taskId)) {
            return 'Error: taskId is required for start action.';
        }

        $data = [];
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

        $result = $this->service->startTimeEntry($workspaceId, $taskId, $data);

        return "Timer started on task '{$taskId}'.\n"
            . json_encode($result['data'] ?? $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function stopTimer(string $workspaceId): string
    {
        $result = $this->service->stopTimeEntry($workspaceId);

        return "Timer stopped.\n"
            . json_encode($result['data'] ?? $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function logTime(string $workspaceId, Request $request): string
    {
        $taskId = $request['taskId'] ?? '';
        $start = $request['start'] ?? '';
        $duration = $request['duration'] ?? '';

        if (empty($taskId)) {
            return 'Error: taskId is required for log action.';
        }
        if (empty($start)) {
            return 'Error: start time is required for log action (ISO 8601).';
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
    }

    private function listEntries(Request $request): string
    {
        $taskId = $request['taskId'] ?? '';
        if (empty($taskId)) {
            return 'Error: taskId is required for list action.';
        }

        // Handle custom task IDs
        $effectiveId = $taskId;
        $queryParams = $this->service->withCustomIdParams($taskId);
        if (! empty($queryParams)) {
            $effectiveId .= '?' . http_build_query($queryParams);
        }

        $result = $this->service->getTaskTimeEntries($effectiveId);
        $entries = $result['data'] ?? [];

        if (empty($entries)) {
            return 'No time entries found for this task.';
        }

        $output = array_map(fn (array $e) => [
            'id' => $e['id'] ?? '',
            'user' => $e['user']['username'] ?? '',
            'duration' => isset($e['duration']) ? round((int) $e['duration'] / 60000, 1) . ' min' : '',
            'description' => $e['description'] ?? '',
            'start' => isset($e['start']) ? ClickUpService::fromMillis((int) $e['start']) : '',
            'end' => isset($e['end']) ? ClickUpService::fromMillis((int) $e['end']) : '',
            'billable' => $e['billable'] ?? false,
        ], $entries);

        return json_encode(['count' => count($output), 'entries' => $output], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function currentEntry(string $workspaceId): string
    {
        $result = $this->service->getCurrentTimeEntry($workspaceId);
        $data = $result['data'] ?? null;

        if (empty($data)) {
            return 'No timer is currently running.';
        }

        return "Currently running timer:\n"
            . json_encode([
                'id' => $data['id'] ?? '',
                'task' => $data['task']['name'] ?? $data['task_id'] ?? '',
                'description' => $data['description'] ?? '',
                'start' => isset($data['start']) ? ClickUpService::fromMillis((int) $data['start']) : '',
                'billable' => $data['billable'] ?? false,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema
                ->string()
                ->description('Action: "start", "stop", "log", "list", or "current".')
                ->required(),
            'taskId' => $schema
                ->string()
                ->description('Task ID (required for start, log, list).'),
            'duration' => $schema
                ->string()
                ->description('Duration in milliseconds (required for log action).'),
            'start' => $schema
                ->string()
                ->description('Start time in ISO 8601 format (required for log action).'),
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
