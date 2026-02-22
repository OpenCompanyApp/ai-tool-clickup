<?php

namespace OpenCompany\AiToolClickUp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolClickUp\ClickUpService;

class ClickUpListChannels implements Tool
{
    public function __construct(
        private ClickUpService $service,
    ) {}

    public function description(): string
    {
        return 'List all chat channels in the ClickUp workspace.';
    }

    public function handle(Request $request): string
    {
        try {
            if (! $this->service->isConfigured()) {
                return 'Error: ClickUp integration is not configured.';
            }

            $workspaceId = $request['workspaceId'] ?? $this->service->getWorkspaceId();
            if (empty($workspaceId)) {
                return 'Error: Workspace ID is required. Configure it in settings or pass workspaceId.';
            }

            $params = [];
            if (isset($request['cursor'])) {
                $params['cursor'] = $request['cursor'];
            }

            $result = $this->service->getChatChannels($workspaceId, $params);
            $channels = $result['channels'] ?? [];

            if (empty($channels)) {
                return 'No chat channels found.';
            }

            $output = array_map(fn (array $ch) => [
                'id' => $ch['id'] ?? '',
                'name' => $ch['name'] ?? '',
                'type' => $ch['type'] ?? '',
                'member_count' => $ch['member_count'] ?? 0,
            ], $channels);

            $response = ['count' => count($output), 'channels' => $output];
            if (! empty($result['next_cursor'])) {
                $response['next_cursor'] = $result['next_cursor'];
            }

            return json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            return "Error listing channels: {$e->getMessage()}";
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'cursor' => $schema
                ->string()
                ->description('Pagination cursor.'),
            'workspaceId' => $schema
                ->string()
                ->description('Workspace ID. Uses configured default if omitted.'),
        ];
    }
}
