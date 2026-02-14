<?php

namespace OpenCompany\AiToolClickUp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolClickUp\ClickUpService;

class ClickUpManageDocumentPages implements Tool
{
    public function __construct(
        private ClickUpService $service,
    ) {}

    public function description(): string
    {
        return <<<'MD'
        Manage pages within a ClickUp document. Actions:
        - **list**: List all pages in a document.
        - **get**: Get the content of specific pages.
        - **create**: Create a new page in a document.
        - **update**: Update an existing page (replace, append, or prepend content).
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
                return 'Error: action is required (list, get, create, update).';
            }

            $documentId = $request['documentId'] ?? '';
            if (empty($documentId)) {
                return 'Error: documentId is required.';
            }

            $workspaceId = $request['workspaceId'] ?? $this->service->getWorkspaceId();
            if (empty($workspaceId)) {
                return 'Error: Workspace ID is required for documents. Configure it in settings or pass workspaceId.';
            }

            return match ($action) {
                'list' => $this->listPages($workspaceId, $documentId, $request),
                'get' => $this->getPages($workspaceId, $documentId, $request),
                'create' => $this->createPage($workspaceId, $documentId, $request),
                'update' => $this->updatePage($workspaceId, $documentId, $request),
                default => "Error: Unknown action '{$action}'. Use: list, get, create, update.",
            };
        } catch (\Throwable $e) {
            return "Error with document pages: {$e->getMessage()}";
        }
    }

    private function listPages(string $workspaceId, string $documentId, Request $request): string
    {
        $params = [];
        if (isset($request['maxDepth'])) {
            $params['max_page_depth'] = (int) $request['maxDepth'];
        }

        $result = $this->service->listDocumentPages($workspaceId, $documentId, $params);
        $pages = $result['pages'] ?? [];

        if (empty($pages)) {
            return 'No pages found in this document.';
        }

        $output = array_map(fn (array $p) => [
            'id' => $p['id'] ?? '',
            'name' => $p['name'] ?? '',
            'sub_title' => $p['sub_title'] ?? '',
        ], $pages);

        return json_encode(['count' => count($output), 'pages' => $output], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function getPages(string $workspaceId, string $documentId, Request $request): string
    {
        $pageIds = $request['pageIds'] ?? '';
        if (empty($pageIds)) {
            return 'Error: pageIds is required for get action (comma-separated).';
        }

        $ids = is_string($pageIds) ? explode(',', $pageIds) : $pageIds;

        $params = [
            'page_ids' => array_map('trim', $ids),
        ];

        if (isset($request['contentFormat'])) {
            $params['content_format'] = $request['contentFormat'];
        }

        $result = $this->service->getDocumentPages($workspaceId, $documentId, $params);
        $pages = $result['pages'] ?? [];

        if (empty($pages)) {
            return 'No page content found.';
        }

        return json_encode(['pages' => $pages], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function createPage(string $workspaceId, string $documentId, Request $request): string
    {
        $name = $request['name'] ?? '';
        $content = $request['content'] ?? '';

        if (empty($name)) {
            return 'Error: name is required for create action.';
        }

        $data = [
            'name' => $name,
            'content' => $content,
            'content_format' => $request['contentFormat'] ?? 'text/md',
        ];

        if (isset($request['subTitle'])) {
            $data['sub_title'] = $request['subTitle'];
        }
        if (isset($request['parentPageId'])) {
            $data['parent_page_id'] = $request['parentPageId'];
        }

        $result = $this->service->createDocumentPage($workspaceId, $documentId, $data);

        return "Page '{$name}' created successfully.\n"
            . json_encode(['id' => $result['id'] ?? ''], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function updatePage(string $workspaceId, string $documentId, Request $request): string
    {
        $pageId = $request['pageId'] ?? '';
        if (empty($pageId)) {
            return 'Error: pageId is required for update action.';
        }

        $data = [];

        if (isset($request['name'])) {
            $data['name'] = $request['name'];
        }
        if (isset($request['subTitle'])) {
            $data['sub_title'] = $request['subTitle'];
        }
        if (isset($request['content'])) {
            $data['content'] = $request['content'];
            $data['content_format'] = $request['contentFormat'] ?? 'text/md';
            $data['content_edit_mode'] = $request['editMode'] ?? 'replace';
        }

        if (empty($data)) {
            return 'Error: At least one field to update (name, subTitle, content) is required.';
        }

        $this->service->updateDocumentPage($workspaceId, $documentId, $pageId, $data);

        return "Page '{$pageId}' updated successfully.";
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema
                ->string()
                ->description('Action: "list", "get", "create", or "update".')
                ->required(),
            'documentId' => $schema
                ->string()
                ->description('Document ID.')
                ->required(),
            'pageId' => $schema
                ->string()
                ->description('Page ID (required for update).'),
            'pageIds' => $schema
                ->string()
                ->description('Comma-separated page IDs (required for get).'),
            'name' => $schema
                ->string()
                ->description('Page name/title (required for create).'),
            'content' => $schema
                ->string()
                ->description('Page content.'),
            'contentFormat' => $schema
                ->string()
                ->description('Content format: "text/md" (default) or "text/html".'),
            'editMode' => $schema
                ->string()
                ->description('For update: "replace" (default), "append", or "prepend".'),
            'subTitle' => $schema
                ->string()
                ->description('Page subtitle.'),
            'parentPageId' => $schema
                ->string()
                ->description('Parent page ID for creating sub-pages.'),
            'maxDepth' => $schema
                ->integer()
                ->description('Max page depth for list action (-1 for unlimited).'),
            'workspaceId' => $schema
                ->string()
                ->description('Workspace ID. Uses configured default if omitted.'),
        ];
    }
}
