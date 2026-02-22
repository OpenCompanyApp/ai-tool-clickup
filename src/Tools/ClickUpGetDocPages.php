<?php

namespace OpenCompany\AiToolClickUp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolClickUp\ClickUpService;

class ClickUpGetDocPages implements Tool
{
    public function __construct(
        private ClickUpService $service,
    ) {}

    public function description(): string
    {
        return 'Get the content of specific pages from a ClickUp document.';
    }

    public function handle(Request $request): string
    {
        try {
            if (! $this->service->isConfigured()) {
                return 'Error: ClickUp integration is not configured.';
            }

            $documentId = $request['documentId'] ?? '';
            if (empty($documentId)) {
                return 'Error: documentId is required.';
            }

            $pageIds = $request['pageIds'] ?? '';
            if (empty($pageIds)) {
                return 'Error: pageIds is required (comma-separated).';
            }

            $workspaceId = $request['workspaceId'] ?? $this->service->getWorkspaceId();
            if (empty($workspaceId)) {
                return 'Error: Workspace ID is required. Configure it in settings or pass workspaceId.';
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
        } catch (\Throwable $e) {
            return "Error getting document pages: {$e->getMessage()}";
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'documentId' => $schema
                ->string()
                ->description('Document ID.')
                ->required(),
            'pageIds' => $schema
                ->string()
                ->description('Comma-separated page IDs to retrieve.')
                ->required(),
            'contentFormat' => $schema
                ->string()
                ->description('Content format: "text/md" (default) or "text/html".'),
            'workspaceId' => $schema
                ->string()
                ->description('Workspace ID. Uses configured default if omitted.'),
        ];
    }
}
