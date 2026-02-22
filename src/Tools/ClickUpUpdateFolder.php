<?php

namespace OpenCompany\AiToolClickUp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolClickUp\ClickUpService;

class ClickUpUpdateFolder implements Tool
{
    public function __construct(
        private ClickUpService $service,
    ) {}

    public function description(): string
    {
        return "Update a ClickUp folder's name.";
    }

    public function handle(Request $request): string
    {
        try {
            if (! $this->service->isConfigured()) {
                return 'Error: ClickUp integration is not configured.';
            }

            $folderId = $request['folderId'] ?? '';
            $name = $request['name'] ?? '';

            if (empty($folderId)) {
                return 'Error: folderId is required.';
            }
            if (empty($name)) {
                return 'Error: name is required.';
            }

            $result = $this->service->updateFolder($folderId, ['name' => $name]);

            return "Folder updated successfully.\n"
                . json_encode(['id' => $result['id'] ?? '', 'name' => $result['name'] ?? ''], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            return "Error updating folder: {$e->getMessage()}";
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
            'name' => $schema
                ->string()
                ->description('New folder name.')
                ->required(),
        ];
    }
}
