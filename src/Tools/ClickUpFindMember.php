<?php

namespace OpenCompany\AiToolClickUp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolClickUp\ClickUpService;

class ClickUpFindMember implements Tool
{
    public function __construct(
        private ClickUpService $service,
    ) {}

    public function description(): string
    {
        return 'Find a workspace member by name or email.';
    }

    public function handle(Request $request): string
    {
        try {
            if (! $this->service->isConfigured()) {
                return 'Error: ClickUp integration is not configured.';
            }

            $query = $request['query'] ?? '';
            if (empty($query)) {
                return 'Error: "query" parameter is required.';
            }

            $members = $this->getAllMembers();
            $queryLower = strtolower($query);

            $matches = array_filter($members, function (array $m) use ($queryLower) {
                return str_contains(strtolower($m['username'] ?? ''), $queryLower)
                    || str_contains(strtolower($m['email'] ?? ''), $queryLower)
                    || str_contains(strtolower($m['initials'] ?? ''), $queryLower);
            });

            if (empty($matches)) {
                return "No member found matching '{$query}'.";
            }

            $output = array_map(fn (array $m) => [
                'id' => $m['id'] ?? '',
                'username' => $m['username'] ?? '',
                'email' => $m['email'] ?? '',
            ], array_values($matches));

            return json_encode(['matches' => $output], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            return "Error finding member: {$e->getMessage()}";
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
                ->description('Name or email to search for.')
                ->required(),
        ];
    }
}
