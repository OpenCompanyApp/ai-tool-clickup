<?php

namespace OpenCompany\AiToolClickUp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolClickUp\ClickUpService;

class ClickUpUpdateDocPage implements Tool
{
    public function __construct(
        private ClickUpService $service,
    ) {}

    public function description(): string
    {
        return 'Update an existing page in a ClickUp document. Supports replace, append, or prepend content.';
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

            $pageId = $request['pageId'] ?? '';
            if (empty($pageId)) {
                return 'Error: pageId is required.';
            }

            $workspaceId = $request['workspaceId'] ?? $this->service->getWorkspaceId();
            if (empty($workspaceId)) {
                return 'Error: Workspace ID is required. Configure it in settings or pass workspaceId.';
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
        } catch (\Throwable $e) {
            return "Error updating document page: {$e->getMessage()}";
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
            'pageId' => $schema
                ->string()
                ->description('Page ID.')
                ->required(),
            'name' => $schema
                ->string()
                ->description('New page name/title.'),
            'content' => $schema
                ->string()
                ->description('New page content.'),
            'contentFormat' => $schema
                ->string()
                ->description('Content format: "text/md" (default) or "text/html".'),
            'editMode' => $schema
                ->string()
                ->description('Edit mode: "replace" (default), "append", or "prepend".'),
            'subTitle' => $schema
                ->string()
                ->description('New page subtitle.'),
            'workspaceId' => $schema
                ->string()
                ->description('Workspace ID. Uses configured default if omitted.'),
        ];
    }
}
