<?php

namespace OpenCompany\AiToolClickUp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolClickUp\ClickUpService;

class ClickUpCurrentTimeEntry implements Tool
{
    public function __construct(
        private ClickUpService $service,
    ) {}

    public function description(): string
    {
        return 'Get the currently running time tracking entry, if any.';
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
        } catch (\Throwable $e) {
            return "Error getting current time entry: {$e->getMessage()}";
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'workspaceId' => $schema
                ->string()
                ->description('Workspace ID. Uses configured default if omitted.'),
        ];
    }
}
