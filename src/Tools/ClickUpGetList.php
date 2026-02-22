<?php

namespace OpenCompany\AiToolClickUp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolClickUp\ClickUpService;

class ClickUpGetList implements Tool
{
    public function __construct(
        private ClickUpService $service,
    ) {}

    public function description(): string
    {
        return 'Get details of a ClickUp list by ID.';
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

            $result = $this->service->getList($listId);

            return json_encode([
                'id' => $result['id'] ?? '',
                'name' => $result['name'] ?? '',
                'content' => $result['content'] ?? '',
                'status' => $result['status'] ?? null,
                'task_count' => $result['task_count'] ?? 0,
                'space' => [
                    'id' => $result['space']['id'] ?? '',
                    'name' => $result['space']['name'] ?? '',
                ],
                'folder' => [
                    'id' => $result['folder']['id'] ?? '',
                    'name' => $result['folder']['name'] ?? '',
                ],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            return "Error getting list: {$e->getMessage()}";
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
                ->description('List ID.')
                ->required(),
        ];
    }
}
