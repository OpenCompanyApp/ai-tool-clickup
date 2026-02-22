<?php

namespace OpenCompany\AiToolClickUp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolClickUp\ClickUpService;

class ClickUpUpdateList implements Tool
{
    public function __construct(
        private ClickUpService $service,
    ) {}

    public function description(): string
    {
        return "Update a ClickUp list's name, content, or status.";
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

            $data = [];
            if (isset($request['name'])) {
                $data['name'] = $request['name'];
            }
            if (isset($request['content'])) {
                $data['content'] = $request['content'];
            }
            if (isset($request['status'])) {
                $data['status'] = $request['status'];
            }

            if (empty($data)) {
                return 'Error: At least one field to update (name, content, status) is required.';
            }

            $result = $this->service->updateList($listId, $data);

            return "List updated successfully.\n"
                . json_encode(['id' => $result['id'] ?? '', 'name' => $result['name'] ?? ''], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            return "Error updating list: {$e->getMessage()}";
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
            'name' => $schema
                ->string()
                ->description('New list name.'),
            'content' => $schema
                ->string()
                ->description('New list description/content.'),
            'status' => $schema
                ->string()
                ->description('New list status.'),
        ];
    }
}
