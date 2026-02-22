<?php

namespace OpenCompany\AiToolClickUp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolClickUp\ClickUpService;

class ClickUpListDocPages implements Tool
{
    public function __construct(
        private ClickUpService $service,
    ) {}

    public function description(): string
    {
        return 'List all pages in a ClickUp document.';
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

            $workspaceId = $request['workspaceId'] ?? $this->service->getWorkspaceId();
            if (empty($workspaceId)) {
                return 'Error: Workspace ID is required. Configure it in settings or pass workspaceId.';
            }

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
        } catch (\Throwable $e) {
            return "Error listing document pages: {$e->getMessage()}";
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
            'maxDepth' => $schema
                ->integer()
                ->description('Max page depth (-1 for unlimited).'),
            'workspaceId' => $schema
                ->string()
                ->description('Workspace ID. Uses configured default if omitted.'),
        ];
    }
}
