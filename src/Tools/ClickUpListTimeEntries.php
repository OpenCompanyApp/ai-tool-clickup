<?php

namespace OpenCompany\AiToolClickUp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolClickUp\ClickUpService;

class ClickUpListTimeEntries implements Tool
{
    public function __construct(
        private ClickUpService $service,
    ) {}

    public function description(): string
    {
        return 'Get all time entries for a ClickUp task.';
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
        } catch (\Throwable $e) {
            return "Error listing time entries: {$e->getMessage()}";
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
                ->description('Task ID. Supports custom IDs like "DEV-42".')
                ->required(),
        ];
    }
}
