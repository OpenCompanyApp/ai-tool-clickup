<?php

namespace OpenCompany\AiToolClickUp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolClickUp\ClickUpService;

class ClickUpAttachFile implements Tool
{
    public function __construct(
        private ClickUpService $service,
    ) {}

    public function description(): string
    {
        return <<<'MD'
        Attach a file to a ClickUp task via URL.
        The file URL must be publicly accessible (http/https).
        MD;
    }

    public function handle(Request $request): string
    {
        try {
            if (! $this->service->isConfigured()) {
                return 'Error: ClickUp integration is not configured.';
            }

            $taskId = $request['taskId'] ?? '';
            $fileUrl = $request['fileUrl'] ?? '';

            if (empty($taskId)) {
                return 'Error: taskId is required.';
            }
            if (empty($fileUrl)) {
                return 'Error: fileUrl is required.';
            }

            // Handle custom task IDs
            $effectiveId = $taskId;
            $queryParams = $this->service->withCustomIdParams($taskId);
            if (! empty($queryParams)) {
                $effectiveId .= '?' . http_build_query($queryParams);
            }

            $result = $this->service->attachFileToTask($effectiveId, [
                'url' => $fileUrl,
            ]);

            return "File attached to task successfully.\n"
                . json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            return "Error attaching file: {$e->getMessage()}";
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
                ->description('Task ID to attach the file to. Supports custom IDs like "DEV-42".')
                ->required(),
            'fileUrl' => $schema
                ->string()
                ->description('Public URL of the file to attach (http/https).')
                ->required(),
        ];
    }
}
