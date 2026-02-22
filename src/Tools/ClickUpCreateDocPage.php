<?php

namespace OpenCompany\AiToolClickUp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolClickUp\ClickUpService;

class ClickUpCreateDocPage implements Tool
{
    public function __construct(
        private ClickUpService $service,
    ) {}

    public function description(): string
    {
        return 'Create a new page in a ClickUp document.';
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

            $name = $request['name'] ?? '';
            if (empty($name)) {
                return 'Error: name is required.';
            }

            $workspaceId = $request['workspaceId'] ?? $this->service->getWorkspaceId();
            if (empty($workspaceId)) {
                return 'Error: Workspace ID is required. Configure it in settings or pass workspaceId.';
            }

            $content = $request['content'] ?? '';

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
        } catch (\Throwable $e) {
            return "Error creating document page: {$e->getMessage()}";
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
            'name' => $schema
                ->string()
                ->description('Page name/title.')
                ->required(),
            'content' => $schema
                ->string()
                ->description('Page content.'),
            'contentFormat' => $schema
                ->string()
                ->description('Content format: "text/md" (default) or "text/html".'),
            'subTitle' => $schema
                ->string()
                ->description('Page subtitle.'),
            'parentPageId' => $schema
                ->string()
                ->description('Parent page ID for creating sub-pages.'),
            'workspaceId' => $schema
                ->string()
                ->description('Workspace ID. Uses configured default if omitted.'),
        ];
    }
}
