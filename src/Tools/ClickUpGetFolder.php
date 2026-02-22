<?php

namespace OpenCompany\AiToolClickUp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolClickUp\ClickUpService;

class ClickUpGetFolder implements Tool
{
    public function __construct(
        private ClickUpService $service,
    ) {}

    public function description(): string
    {
        return 'Get details of a ClickUp folder by ID, including its lists.';
    }

    public function handle(Request $request): string
    {
        try {
            if (! $this->service->isConfigured()) {
                return 'Error: ClickUp integration is not configured.';
            }

            $folderId = $request['folderId'] ?? '';
            if (empty($folderId)) {
                return 'Error: folderId is required.';
            }

            $result = $this->service->getFolder($folderId);

            $lists = array_map(fn (array $l) => [
                'id' => $l['id'] ?? '',
                'name' => $l['name'] ?? '',
            ], $result['lists'] ?? []);

            return json_encode([
                'id' => $result['id'] ?? '',
                'name' => $result['name'] ?? '',
                'space' => [
                    'id' => $result['space']['id'] ?? '',
                    'name' => $result['space']['name'] ?? '',
                ],
                'lists' => $lists,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            return "Error getting folder: {$e->getMessage()}";
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'folderId' => $schema
                ->string()
                ->description('Folder ID.')
                ->required(),
        ];
    }
}
