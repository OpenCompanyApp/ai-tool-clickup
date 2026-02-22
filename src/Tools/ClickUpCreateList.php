<?php

namespace OpenCompany\AiToolClickUp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolClickUp\ClickUpService;

class ClickUpCreateList implements Tool
{
    public function __construct(
        private ClickUpService $service,
    ) {}

    public function description(): string
    {
        return 'Create a new list in a ClickUp space.';
    }

    public function handle(Request $request): string
    {
        try {
            if (! $this->service->isConfigured()) {
                return 'Error: ClickUp integration is not configured.';
            }

            $spaceId = $request['spaceId'] ?? '';
            $name = $request['name'] ?? '';

            if (empty($spaceId)) {
                return 'Error: spaceId is required.';
            }
            if (empty($name)) {
                return 'Error: name is required.';
            }

            $data = ['name' => $name];
            if (isset($request['content'])) {
                $data['content'] = $request['content'];
            }
            if (isset($request['status'])) {
                $data['status'] = $request['status'];
            }

            $result = $this->service->createList($spaceId, $data);

            return "List '{$name}' created successfully.\n"
                . json_encode(['id' => $result['id'] ?? '', 'name' => $result['name'] ?? ''], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            return "Error creating list: {$e->getMessage()}";
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'spaceId' => $schema
                ->string()
                ->description('Space ID to create the list in.')
                ->required(),
            'name' => $schema
                ->string()
                ->description('List name.')
                ->required(),
            'content' => $schema
                ->string()
                ->description('List description/content.'),
            'status' => $schema
                ->string()
                ->description('List status.'),
        ];
    }
}
