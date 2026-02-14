<?php

namespace OpenCompany\AiToolClickUp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolClickUp\ClickUpService;

class ClickUpMembers implements Tool
{
    public function __construct(
        private ClickUpService $service,
    ) {}

    public function description(): string
    {
        return <<<'MD'
        Manage workspace members. Actions:
        - **list**: Get all workspace members with IDs, names, and emails.
        - **find**: Find a member by name or email.
        - **resolve**: Convert names/emails to user IDs for assigning tasks.
        MD;
    }

    public function handle(Request $request): string
    {
        try {
            if (! $this->service->isConfigured()) {
                return 'Error: ClickUp integration is not configured.';
            }

            $action = $request['action'] ?? 'list';

            return match ($action) {
                'list' => $this->listMembers($request),
                'find' => $this->findMember($request),
                'resolve' => $this->resolveMembers($request),
                default => "Error: Unknown action '{$action}'. Use: list, find, resolve.",
            };
        } catch (\Throwable $e) {
            return "Error with members: {$e->getMessage()}";
        }
    }

    private function listMembers(Request $request): string
    {
        $members = $this->getAllMembers();

        if (empty($members)) {
            return 'No members found.';
        }

        $output = array_map(fn (array $m) => [
            'id' => $m['id'] ?? '',
            'username' => $m['username'] ?? '',
            'email' => $m['email'] ?? '',
            'role' => $m['role'] ?? '',
        ], $members);

        return json_encode(['count' => count($output), 'members' => $output], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function findMember(Request $request): string
    {
        $query = $request['query'] ?? '';
        if (empty($query)) {
            return 'Error: "query" parameter is required for find action.';
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
    }

    private function resolveMembers(Request $request): string
    {
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
            'action' => $schema
                ->string()
                ->description('Action: "list" (all members), "find" (search by name/email), "resolve" (names to IDs).')
                ->required(),
            'query' => $schema
                ->string()
                ->description('For find: name or email to search. For resolve: comma-separated names or emails.'),
        ];
    }
}
