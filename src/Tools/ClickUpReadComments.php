<?php

namespace OpenCompany\AiToolClickUp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolClickUp\ClickUpService;

class ClickUpReadComments implements Tool
{
    public function __construct(
        private ClickUpService $service,
    ) {}

    public function description(): string
    {
        return 'Get all comments on a ClickUp task. Supports pagination.';
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

            // Handle custom task IDs
            $effectiveId = $taskId;
            $queryParams = $this->service->withCustomIdParams($taskId);
            if (! empty($queryParams)) {
                $effectiveId .= '?' . http_build_query($queryParams);
            }

            $params = [];
            if (isset($request['start'])) {
                $params['start'] = $request['start'];
            }
            if (isset($request['startId'])) {
                $params['start_id'] = $request['startId'];
            }

            $result = $this->service->getTaskComments($effectiveId, $params);
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
        } catch (\Throwable $e) {
            return "Error reading comments: {$e->getMessage()}";
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
            'start' => $schema
                ->string()
                ->description('Timestamp (ms) for pagination.'),
            'startId' => $schema
                ->string()
                ->description('Comment ID for pagination.'),
        ];
    }
}
