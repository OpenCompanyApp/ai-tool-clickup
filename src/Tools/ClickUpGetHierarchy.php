<?php

namespace OpenCompany\AiToolClickUp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolClickUp\ClickUpService;

class ClickUpGetHierarchy implements Tool
{
    public function __construct(
        private ClickUpService $service,
    ) {}

    public function description(): string
    {
        return <<<'MD'
        Get the ClickUp workspace hierarchy — spaces, folders, and lists.
        Returns a tree structure with IDs and names for navigation.
        Optionally filter to specific space IDs.
        MD;
    }

    public function handle(Request $request): string
    {
        try {
            if (! $this->service->isConfigured()) {
                return 'Error: ClickUp integration is not configured.';
            }

            $workspaceId = $request['workspaceId'] ?? $this->service->getWorkspaceId();

            if (empty($workspaceId)) {
                // Auto-detect from teams endpoint
                $teams = $this->service->getTeams();
                $teamList = $teams['teams'] ?? [];
                if (empty($teamList)) {
                    return 'Error: No workspaces found. Check your API token.';
                }
                $workspaceId = $teamList[0]['id'];
            }

            $spacesResponse = $this->service->getSpaces($workspaceId);
            $spaces = $spacesResponse['spaces'] ?? [];

            // Optional filter
            $filterSpaceIds = isset($request['spaceIds'])
                ? (is_string($request['spaceIds']) ? explode(',', $request['spaceIds']) : $request['spaceIds'])
                : null;

            $tree = [];
            foreach ($spaces as $space) {
                if ($filterSpaceIds && ! in_array($space['id'], $filterSpaceIds)) {
                    continue;
                }

                $spaceNode = [
                    'id' => $space['id'],
                    'name' => $space['name'],
                    'folders' => [],
                    'lists' => [],
                ];

                // Get folders
                $foldersResponse = $this->service->getFolders($space['id']);
                foreach ($foldersResponse['folders'] ?? [] as $folder) {
                    $folderNode = [
                        'id' => $folder['id'],
                        'name' => $folder['name'],
                        'lists' => [],
                    ];

                    foreach ($folder['lists'] ?? [] as $list) {
                        $folderNode['lists'][] = [
                            'id' => $list['id'],
                            'name' => $list['name'],
                        ];
                    }

                    $spaceNode['folders'][] = $folderNode;
                }

                // Get folderless lists
                $listsResponse = $this->service->getSpaceLists($space['id']);
                foreach ($listsResponse['lists'] ?? [] as $list) {
                    $spaceNode['lists'][] = [
                        'id' => $list['id'],
                        'name' => $list['name'],
                    ];
                }

                $tree[] = $spaceNode;
            }

            return json_encode(['workspace_id' => $workspaceId, 'spaces' => $tree], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            return "Error getting hierarchy: {$e->getMessage()}";
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'workspaceId' => $schema
                ->string()
                ->description('Workspace/team ID. Uses configured default if omitted.'),
            'spaceIds' => $schema
                ->string()
                ->description('Comma-separated space IDs to filter. Omit to get all spaces.'),
        ];
    }
}
