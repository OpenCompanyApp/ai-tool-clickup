<?php

namespace OpenCompany\AiToolClickUp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolClickUp\ClickUpService;

class ClickUpSendMessage implements Tool
{
    public function __construct(
        private ClickUpService $service,
    ) {}

    public function description(): string
    {
        return 'Send a message to a ClickUp chat channel.';
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

            $channelId = $request['channelId'] ?? '';
            $content = $request['content'] ?? '';

            if (empty($channelId)) {
                return 'Error: channelId is required.';
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
        } catch (\Throwable $e) {
            return "Error sending message: {$e->getMessage()}";
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'channelId' => $schema
                ->string()
                ->description('Channel ID.')
                ->required(),
            'content' => $schema
                ->string()
                ->description('Message content, supports markdown.')
                ->required(),
            'contentFormat' => $schema
                ->string()
                ->description('Content format: "text/md" (default) or "text/plain".'),
            'type' => $schema
                ->string()
                ->description('Message type: "message" (default) or "post".'),
            'postTitle' => $schema
                ->string()
                ->description('Post title (required if type is "post").'),
            'workspaceId' => $schema
                ->string()
                ->description('Workspace ID. Uses configured default if omitted.'),
        ];
    }
}
