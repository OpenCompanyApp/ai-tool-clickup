<?php

namespace OpenCompany\AiToolClickUp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolClickUp\ClickUpService;

class ClickUpManageFolder implements Tool
{
    public function __construct(
        private ClickUpService $service,
    ) {}

    public function description(): string
    {
        return <<<'MD'
        Create, get, or update ClickUp folders. Actions:
        - **create**: Create a folder in a space (requires spaceId and name).
        - **get**: Get folder details by ID.
        - **update**: Update a folder's name.
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
                return 'Error: action is required (create, get, update).';
            }

            return match ($action) {
                'create' => $this->createFolder($request),
                'get' => $this->getFolder($request),
                'update' => $this->updateFolder($request),
                default => "Error: Unknown action '{$action}'. Use: create, get, update.",
            };
        } catch (\Throwable $e) {
            return "Error managing folder: {$e->getMessage()}";
        }
    }

    private function createFolder(Request $request): string
    {
        $spaceId = $request['spaceId'] ?? '';
        $name = $request['name'] ?? '';

        if (empty($spaceId)) {
            return 'Error: spaceId is required for create action.';
        }
        if (empty($name)) {
            return 'Error: name is required.';
        }

        $data = ['name' => $name];

        $result = $this->service->createFolder($spaceId, $data);

        return "Folder '{$name}' created successfully.\n"
            . json_encode(['id' => $result['id'] ?? '', 'name' => $result['name'] ?? ''], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function getFolder(Request $request): string
    {
        $folderId = $request['folderId'] ?? '';
        if (empty($folderId)) {
            return 'Error: folderId is required for get action.';
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
    }

    private function updateFolder(Request $request): string
    {
        $folderId = $request['folderId'] ?? '';
        $name = $request['name'] ?? '';

        if (empty($folderId)) {
            return 'Error: folderId is required for update action.';
        }
        if (empty($name)) {
            return 'Error: name is required for update action.';
        }

        $result = $this->service->updateFolder($folderId, ['name' => $name]);

        return "Folder updated successfully.\n"
            . json_encode(['id' => $result['id'] ?? '', 'name' => $result['name'] ?? ''], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema
                ->string()
                ->description('Action: "create", "get", or "update".')
                ->required(),
            'spaceId' => $schema
                ->string()
                ->description('Space ID (required for create).'),
            'folderId' => $schema
                ->string()
                ->description('Folder ID (required for get and update).'),
            'name' => $schema
                ->string()
                ->description('Folder name.'),
        ];
    }
}
