<?php

namespace OpenCompany\AiToolClickUp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolClickUp\ClickUpService;

class ClickUpAddComment implements Tool
{
    public function __construct(
        private ClickUpService $service,
    ) {}

    public function description(): string
    {
        return 'Add a new comment to a ClickUp task.';
    }

    public function handle(Request $request): string
    {
        try {
            if (! $this->service->isConfigured()) {
                return 'Error: ClickUp integration is not configured.';
            }

            $taskId = $request['taskId'] ?? '';
            if (empty($taskId)) {
                return 'Error: taskId is required.';
            }

            $text = $request['commentText'] ?? '';
            if (empty($text)) {
                return 'Error: commentText is required.';
            }

            // Handle custom task IDs
            $effectiveId = $taskId;
            $queryParams = $this->service->withCustomIdParams($taskId);
            if (! empty($queryParams)) {
                $effectiveId .= '?' . http_build_query($queryParams);
            }

            $data = [
                'comment_text' => $text,
            ];

            if (isset($request['assignee'])) {
                $data['assignee'] = (int) $request['assignee'];
            }

            if (isset($request['notifyAll']) && $request['notifyAll']) {
                $data['notify_all'] = true;
            }

            $result = $this->service->createTaskComment($effectiveId, $data);

            return "Comment added successfully.\n"
                . json_encode(['id' => $result['id'] ?? ''], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            return "Error adding comment: {$e->getMessage()}";
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'taskId' => $schema
                ->string()
                ->description('Task ID. Supports custom IDs like "DEV-42".')
                ->required(),
            'commentText' => $schema
                ->string()
                ->description('Comment text.')
                ->required(),
            'assignee' => $schema
                ->string()
                ->description('User ID to assign the comment to.'),
            'notifyAll' => $schema
                ->boolean()
                ->description('Notify all assignees. Default: false.'),
        ];
    }
}
