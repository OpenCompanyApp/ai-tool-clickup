<?php

namespace OpenCompany\AiToolClickUp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolClickUp\ClickUpService;

class ClickUpListMembers implements Tool
{
    public function __construct(
        private ClickUpService $service,
    ) {}

    public function description(): string
    {
        return 'Get all workspace members with their IDs, names, emails, and roles.';
    }

    public function handle(Request $request): string
    {
        try {
            if (! $this->service->isConfigured()) {
                return 'Error: ClickUp integration is not configured.';
            }

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
        } catch (\Throwable $e) {
            return "Error listing members: {$e->getMessage()}";
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
        return [];
    }
}
