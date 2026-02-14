<?php

namespace OpenCompany\AiToolClickUp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolClickUp\ClickUpService;

class ClickUpManageTags implements Tool
{
    public function __construct(
        private ClickUpService $service,
    ) {}

    public function description(): string
    {
        return <<<'MD'
        Add or remove tags on a ClickUp task.
        - **add**: Add an existing tag to a task.
        - **remove**: Remove a tag from a task.
        Tags must already exist in the space.
        MD;
    }

    public function handle(Request $request): string
    {
        try {
            if (! $this->service->isConfigured()) {
                return 'Error: ClickUp integration is not configured.';
            }

            $action = $request['action'] ?? '';
            $taskId = $request['taskId'] ?? '';
            $tagName = $request['tagName'] ?? '';

            if (empty($action)) {
                return 'Error: action is required ("add" or "remove").';
            }
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

            return match ($action) {
                'add' => $this->addTag($effectiveId, $tagName),
                'remove' => $this->removeTag($effectiveId, $tagName),
                default => "Error: Unknown action '{$action}'. Use: add, remove.",
            };
        } catch (\Throwable $e) {
            return "Error managing tags: {$e->getMessage()}";
        }
    }

    private function addTag(string $taskId, string $tagName): string
    {
        $this->service->addTagToTask($taskId, $tagName);

        return "Tag '{$tagName}' added to task successfully.";
    }

    private function removeTag(string $taskId, string $tagName): string
    {
        $this->service->removeTagFromTask($taskId, $tagName);

        return "Tag '{$tagName}' removed from task successfully.";
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema
                ->string()
                ->description('Action: "add" or "remove".')
                ->required(),
            'taskId' => $schema
                ->string()
                ->description('Task ID. Supports custom IDs like "DEV-42".')
                ->required(),
            'tagName' => $schema
                ->string()
                ->description('Tag name to add or remove.')
                ->required(),
        ];
    }
}
