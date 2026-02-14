<?php

namespace OpenCompany\AiToolClickUp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolClickUp\ClickUpService;

class ClickUpChat implements Tool
{
    public function __construct(
        private ClickUpService $service,
    ) {}

    public function description(): string
    {
        return <<<'MD'
        Manage ClickUp chat. Actions:
        - **list_channels**: List all chat channels in the workspace.
        - **send_message**: Send a message to a chat channel.
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
                return 'Error: action is required (list_channels, send_message).';
            }

            $workspaceId = $request['workspaceId'] ?? $this->service->getWorkspaceId();
            if (empty($workspaceId)) {
                return 'Error: Workspace ID is required for chat. Configure it in settings or pass workspaceId.';
            }

            return match ($action) {
                'list_channels' => $this->listChannels($workspaceId, $request),
                'send_message' => $this->sendMessage($workspaceId, $request),
                default => "Error: Unknown action '{$action}'. Use: list_channels, send_message.",
            };
        } catch (\Throwable $e) {
            return "Error with chat: {$e->getMessage()}";
        }
    }

    private function listChannels(string $workspaceId, Request $request): string
    {
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
    }

    private function sendMessage(string $workspaceId, Request $request): string
    {
        $channelId = $request['channelId'] ?? '';
        $content = $request['content'] ?? '';

        if (empty($channelId)) {
            return 'Error: channelId is required for send_message action.';
        }
        if (empty($content)) {
            return 'Error: content is required.';
        }

        $data = [
            'content' => $content,
            'content_format' => $request['contentFormat'] ?? 'text/md',
        ];

        if (isset($request['type']) && $request['type'] === 'post') {
            $data['type'] = 'post';
            if (isset($request['postTitle'])) {
                $data['post_title'] = $request['postTitle'];
            }
        }

        $result = $this->service->sendChatMessage($workspaceId, $channelId, $data);

        return "Message sent to channel '{$channelId}' successfully.\n"
            . json_encode(['id' => $result['id'] ?? ''], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema
                ->string()
                ->description('Action: "list_channels" or "send_message".')
                ->required(),
            'channelId' => $schema
                ->string()
                ->description('Channel ID (required for send_message).'),
            'content' => $schema
                ->string()
                ->description('Message content, supports markdown (required for send_message).'),
            'contentFormat' => $schema
                ->string()
                ->description('Content format: "text/md" (default) or "text/plain".'),
            'type' => $schema
                ->string()
                ->description('Message type: "message" (default) or "post".'),
            'postTitle' => $schema
                ->string()
                ->description('Post title (required if type is "post").'),
            'cursor' => $schema
                ->string()
                ->description('Pagination cursor for list_channels.'),
            'workspaceId' => $schema
                ->string()
                ->description('Workspace ID. Uses configured default if omitted.'),
        ];
    }
}
