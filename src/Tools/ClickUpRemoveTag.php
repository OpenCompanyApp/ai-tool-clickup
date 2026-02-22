<?php

namespace OpenCompany\AiToolClickUp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolClickUp\ClickUpService;

class ClickUpRemoveTag implements Tool
{
    public function __construct(
        private ClickUpService $service,
    ) {}

    public function description(): string
    {
        return 'Remove a tag from a ClickUp task.';
    }

    public function handle(Request $request): string
    {
        try {
            if (! $this->service->isConfigured()) {
                return 'Error: ClickUp integration is not configured.';
            }

            $taskId = $request['taskId'] ?? '';
            $tagName = $request['tagName'] ?? '';

            if (empty($taskId)) {
                return 'Error: taskId is required.';
            }
            if (empty($tagName)) {
                return 'Error: tagName is required.';
            }

            // Handle custom task IDs
            $effectiveId = $taskId;
            $queryParams = $this->service->withCustomIdParams($taskId);
            if (! empty($queryParams)) {
                $effectiveId .= '?' . http_build_query($queryParams);
            }

            $this->service->removeTagFromTask($effectiveId, $tagName);

            return "Tag '{$tagName}' removed from task successfully.";
        } catch (\Throwable $e) {
            return "Error removing tag: {$e->getMessage()}";
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
            'tagName' => $schema
                ->string()
                ->description('Tag name to remove.')
                ->required(),
        ];
    }
}
