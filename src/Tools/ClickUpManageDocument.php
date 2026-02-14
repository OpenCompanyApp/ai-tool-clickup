<?php

namespace OpenCompany\AiToolClickUp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolClickUp\ClickUpService;

class ClickUpManageDocument implements Tool
{
    public function __construct(
        private ClickUpService $service,
    ) {}

    public function description(): string
    {
        return <<<'MD'
        Create a ClickUp document in a space, folder, or list.
        Specify the parent container and visibility (PUBLIC or PRIVATE).
        MD;
    }

    public function handle(Request $request): string
    {
        try {
            if (! $this->service->isConfigured()) {
                return 'Error: ClickUp integration is not configured.';
            }

            $name = $request['name'] ?? '';
            $parentId = $request['parentId'] ?? '';
            $parentType = $request['parentType'] ?? '';

            if (empty($name)) {
                return 'Error: name is required.';
            }
            if (empty($parentId)) {
                return 'Error: parentId is required.';
            }
            if (empty($parentType)) {
                return 'Error: parentType is required (space, folder, list).';
            }

            $workspaceId = $request['workspaceId'] ?? $this->service->getWorkspaceId();
            if (empty($workspaceId)) {
                return 'Error: Workspace ID is required for documents. Configure it in settings or pass workspaceId.';
            }

            // Map human-readable parent types to API codes
            $typeMap = [
                'space' => '4',
                'folder' => '5',
                'list' => '6',
                'everything' => '7',
                'workspace' => '12',
            ];

            $apiType = $typeMap[$parentType] ?? $parentType;

            $data = [
                'name' => $name,
                'parent' => [
                    'id' => $parentId,
                    'type' => $apiType,
                ],
                'visibility' => $request['visibility'] ?? 'PUBLIC',
                'create_page' => isset($request['createPage']) ? (bool) $request['createPage'] : true,
            ];

            $result = $this->service->createDocument($workspaceId, $data);

            return "Document '{$name}' created successfully.\n"
                . json_encode([
                    'id' => $result['id'] ?? '',
                    'name' => $result['name'] ?? '',
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            return "Error creating document: {$e->getMessage()}";
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema
                ->string()
                ->description('Document name/title.')
                ->required(),
            'parentId' => $schema
                ->string()
                ->description('ID of the parent container (space, folder, or list).')
                ->required(),
            'parentType' => $schema
                ->string()
                ->description('Type of parent: "space", "folder", "list", "everything", or "workspace".')
                ->required(),
            'visibility' => $schema
                ->string()
                ->description('Document visibility: "PUBLIC" or "PRIVATE". Default: PUBLIC.'),
            'createPage' => $schema
                ->boolean()
                ->description('Create an initial blank page. Default: true.'),
            'workspaceId' => $schema
                ->string()
                ->description('Workspace ID. Uses configured default if omitted.'),
        ];
    }
}
