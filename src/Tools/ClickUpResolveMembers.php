<?php

namespace OpenCompany\AiToolClickUp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolClickUp\ClickUpService;

class ClickUpResolveMembers implements Tool
{
    public function __construct(
        private ClickUpService $service,
    ) {}

    public function description(): string
    {
        return 'Convert member names or emails to ClickUp user IDs for assigning tasks.';
    }

    public function handle(Request $request): string
    {
        try {
            if (! $this->service->isConfigured()) {
                return 'Error: ClickUp integration is not configured.';
            }

            $queries = $request['query'] ?? '';
            if (empty($queries)) {
                return 'Error: "query" parameter is required. Provide comma-separated names or emails.';
            }

            $names = is_string($queries) ? array_map('trim', explode(',', $queries)) : $queries;
            $members = $this->getAllMembers();
            $resolved = [];

            foreach ($names as $name) {
                $nameLower = strtolower($name);
                $found = null;

                foreach ($members as $m) {
                    if (strtolower($m['username'] ?? '') === $nameLower
                        || strtolower($m['email'] ?? '') === $nameLower) {
                        $found = $m['id'] ?? null;
                        break;
                    }
                }

                $resolved[] = [
                    'query' => $name,
                    'id' => $found,
                    'resolved' => $found !== null,
                ];
            }

            return json_encode(['results' => $resolved], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            return "Error resolving members: {$e->getMessage()}";
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getAllMembers(): array
    {
        $response = $this->service->getMembers('');
        $teams = $response['teams'] ?? [];

        $members = [];
        foreach ($teams as $team) {
            foreach ($team['members'] ?? [] as $member) {
                $members[] = $member['user'] ?? $member;
            }
        }

        return $members;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema
                ->string()
                ->description('Comma-separated names or emails to resolve to user IDs.')
                ->required(),
        ];
    }
}
