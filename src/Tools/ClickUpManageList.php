<?php

namespace OpenCompany\AiToolClickUp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolClickUp\ClickUpService;

class ClickUpManageList implements Tool
{
    public function __construct(
        private ClickUpService $service,
    ) {}

    public function description(): string
    {
        return <<<'MD'
        Create, get, or update ClickUp lists. Actions:
        - **create**: Create a list in a space (requires spaceId).
        - **create_in_folder**: Create a list in a folder (requires folderId).
        - **get**: Get list details by ID.
        - **update**: Update a list's name, content, or status.
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
                return 'Error: action is required (create, create_in_folder, get, update).';
            }

            return match ($action) {
                'create' => $this->createList($request),
                'create_in_folder' => $this->createListInFolder($request),
                'get' => $this->getList($request),
                'update' => $this->updateList($request),
                default => "Error: Unknown action '{$action}'. Use: create, create_in_folder, get, update.",
            };
        } catch (\Throwable $e) {
            return "Error managing list: {$e->getMessage()}";
        }
    }

    private function createList(Request $request): string
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
        if (isset($request['content'])) {
            $data['content'] = $request['content'];
        }
        if (isset($request['status'])) {
            $data['status'] = $request['status'];
        }

        $result = $this->service->createList($spaceId, $data);

        return "List '{$name}' created successfully.\n"
            . json_encode(['id' => $result['id'] ?? '', 'name' => $result['name'] ?? ''], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function createListInFolder(Request $request): string
    {
        $folderId = $request['folderId'] ?? '';
        $name = $request['name'] ?? '';

        if (empty($folderId)) {
            return 'Error: folderId is required for create_in_folder action.';
        }
        if (empty($name)) {
            return 'Error: name is required.';
        }

        $data = ['name' => $name];
        if (isset($request['content'])) {
            $data['content'] = $request['content'];
        }
        if (isset($request['status'])) {
            $data['status'] = $request['status'];
        }

        $result = $this->service->createListInFolder($folderId, $data);

        return "List '{$name}' created in folder successfully.\n"
            . json_encode(['id' => $result['id'] ?? '', 'name' => $result['name'] ?? ''], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function getList(Request $request): string
    {
        $listId = $request['listId'] ?? '';
        if (empty($listId)) {
            return 'Error: listId is required for get action.';
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
    }

    private function updateList(Request $request): string
    {
        $listId = $request['listId'] ?? '';
        if (empty($listId)) {
            return 'Error: listId is required for update action.';
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
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema
                ->string()
                ->description('Action: "create", "create_in_folder", "get", or "update".')
                ->required(),
            'spaceId' => $schema
                ->string()
                ->description('Space ID (required for create).'),
            'folderId' => $schema
                ->string()
                ->description('Folder ID (required for create_in_folder).'),
            'listId' => $schema
                ->string()
                ->description('List ID (required for get and update).'),
            'name' => $schema
                ->string()
                ->description('List name (required for create, optional for update).'),
            'content' => $schema
                ->string()
                ->description('List description/content.'),
            'status' => $schema
                ->string()
                ->description('List status.'),
        ];
    }
}
