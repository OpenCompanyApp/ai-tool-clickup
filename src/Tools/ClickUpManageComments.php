<?php

namespace OpenCompany\AiToolClickUp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolClickUp\ClickUpService;

class ClickUpManageComments implements Tool
{
    public function __construct(
        private ClickUpService $service,
    ) {}

    public function description(): string
    {
        return <<<'MD'
        Read or add comments on a ClickUp task.
        - **read**: Get all comments on a task.
        - **add**: Add a new comment to a task.
        MD;
    }

    public function handle(Request $request): string
    {
        try {
            if (! $this->service->isConfigured()) {
                return 'Error: ClickUp integration is not configured.';
            }

            $action = $request['action'] ?? 'read';
            $taskId = $request['taskId'] ?? '';

            if (empty($taskId)) {
                return 'Error: taskId is required.';
            }

            // Handle custom task IDs
            $effectiveId = $taskId;
            $queryParams = $this->service->withCustomIdParams($taskId);
            if (! empty($queryParams)) {
                $effectiveId .= '?' . http_build_query($queryParams);
            }

            return match ($action) {
                'read' => $this->readComments($effectiveId, $request),
                'add' => $this->addComment($effectiveId, $request),
                default => "Error: Unknown action '{$action}'. Use: read, add.",
            };
        } catch (\Throwable $e) {
            return "Error with comments: {$e->getMessage()}";
        }
    }

    private function readComments(string $taskId, Request $request): string
    {
        $params = [];
        if (isset($request['start'])) {
            $params['start'] = $request['start'];
        }
        if (isset($request['startId'])) {
            $params['start_id'] = $request['startId'];
        }

        $result = $this->service->getTaskComments($taskId, $params);
        $comments = $result['comments'] ?? [];

        if (empty($comments)) {
            return 'No comments found on this task.';
        }

        $output = array_map(function (array $c) {
            $text = '';
            foreach ($c['comment'] ?? [] as $block) {
                $text .= $block['text'] ?? '';
            }

            return [
                'id' => $c['id'] ?? '',
                'text' => trim($text),
                'user' => $c['user']['username'] ?? $c['user']['email'] ?? 'Unknown',
                'date' => isset($c['date']) ? ClickUpService::fromMillis((int) $c['date']) : '',
            ];
        }, $comments);

        return json_encode(['count' => count($output), 'comments' => $output], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function addComment(string $taskId, Request $request): string
    {
        $text = $request['commentText'] ?? '';
        if (empty($text)) {
            return 'Error: commentText is required for add action.';
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

        $result = $this->service->createTaskComment($taskId, $data);

        return "Comment added successfully.\n"
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
                ->description('Action: "read" or "add".')
                ->required(),
            'taskId' => $schema
                ->string()
                ->description('Task ID. Supports custom IDs like "DEV-42".')
                ->required(),
            'commentText' => $schema
                ->string()
                ->description('Comment text (required for "add" action).'),
            'assignee' => $schema
                ->string()
                ->description('User ID to assign the comment to.'),
            'notifyAll' => $schema
                ->boolean()
                ->description('Notify all assignees. Default: false.'),
            'start' => $schema
                ->string()
                ->description('Timestamp (ms) for pagination when reading.'),
            'startId' => $schema
                ->string()
                ->description('Comment ID for pagination when reading.'),
        ];
    }
}
