<?php

namespace OpenCompany\AiToolClickUp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ClickUpService
{
    private const BASE_URL = 'https://api.clickup.com/api/v2';

    public function __construct(
        private string $apiToken = '',
        private string $workspaceId = '',
    ) {}

    public function isConfigured(): bool
    {
        return ! empty($this->apiToken);
    }

    public function getWorkspaceId(): string
    {
        return $this->workspaceId;
    }

    // ── Workspace ──────────────────────────────────────────

    /**
     * Get all workspaces (teams) the token has access to.
     *
     * @return array<string, mixed>
     */
    public function getTeams(): array
    {
        return $this->request('GET', '/team');
    }

    /**
     * Get all spaces in a workspace.
     *
     * @return array<string, mixed>
     */
    public function getSpaces(string $teamId): array
    {
        return $this->request('GET', "/team/{$teamId}/space");
    }

    /**
     * Get all folders in a space.
     *
     * @return array<string, mixed>
     */
    public function getFolders(string $spaceId): array
    {
        return $this->request('GET', "/space/{$spaceId}/folder");
    }

    /**
     * Get folderless lists in a space.
     *
     * @return array<string, mixed>
     */
    public function getSpaceLists(string $spaceId): array
    {
        return $this->request('GET', "/space/{$spaceId}/list");
    }

    /**
     * Get lists in a folder.
     *
     * @return array<string, mixed>
     */
    public function getFolderLists(string $folderId): array
    {
        return $this->request('GET', "/folder/{$folderId}/list");
    }

    // ── Search ─────────────────────────────────────────────

    /**
     * Search tasks in a workspace.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function searchTasks(string $teamId, array $params = []): array
    {
        return $this->request('GET', "/team/{$teamId}/task", $params);
    }

    // ── Members ────────────────────────────────────────────

    /**
     * Get all workspace members.
     *
     * @return array<string, mixed>
     */
    public function getMembers(string $teamId): array
    {
        // The teams endpoint returns members embedded — use specific team
        return $this->request('GET', "/team");
    }

    // ── Tasks ──────────────────────────────────────────────

    /**
     * Get tasks in a list.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function getTasks(string $listId, array $params = []): array
    {
        return $this->request('GET', "/list/{$listId}/task", $params);
    }

    /**
     * Get a single task.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function getTask(string $taskId, array $params = []): array
    {
        return $this->request('GET', "/task/{$taskId}", $params);
    }

    /**
     * Create a task.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createTask(string $listId, array $data): array
    {
        return $this->request('POST', "/list/{$listId}/task", $data);
    }

    /**
     * Update a task.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updateTask(string $taskId, array $data): array
    {
        return $this->request('PUT', "/task/{$taskId}", $data);
    }

    /**
     * Delete a task.
     *
     * @return array<string, mixed>
     */
    public function deleteTask(string $taskId): array
    {
        return $this->request('DELETE', "/task/{$taskId}");
    }

    // ── Tags ───────────────────────────────────────────────

    /**
     * Add a tag to a task.
     *
     * @return array<string, mixed>
     */
    public function addTagToTask(string $taskId, string $tagName): array
    {
        return $this->request('POST', "/task/{$taskId}/tag/" . urlencode($tagName));
    }

    /**
     * Remove a tag from a task.
     *
     * @return array<string, mixed>
     */
    public function removeTagFromTask(string $taskId, string $tagName): array
    {
        return $this->request('DELETE', "/task/{$taskId}/tag/" . urlencode($tagName));
    }

    // ── Attachments ────────────────────────────────────────

    /**
     * Attach a file to a task via URL.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function attachFileToTask(string $taskId, array $data): array
    {
        return $this->request('POST', "/task/{$taskId}/attachment", $data);
    }

    // ── Comments ───────────────────────────────────────────

    /**
     * Get task comments.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function getTaskComments(string $taskId, array $params = []): array
    {
        return $this->request('GET', "/task/{$taskId}/comment", $params);
    }

    /**
     * Create a task comment.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createTaskComment(string $taskId, array $data): array
    {
        return $this->request('POST', "/task/{$taskId}/comment", $data);
    }

    // ── Time Tracking ──────────────────────────────────────

    /**
     * Get time entries for a task.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function getTaskTimeEntries(string $taskId, array $params = []): array
    {
        return $this->request('GET', "/task/{$taskId}/time", $params);
    }

    /**
     * Start a timer on a task.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function startTimeEntry(string $teamId, string $taskId, array $data = []): array
    {
        $data['tid'] = $taskId;

        return $this->request('POST', "/team/{$teamId}/time_entries/start", $data);
    }

    /**
     * Stop the running timer.
     *
     * @return array<string, mixed>
     */
    public function stopTimeEntry(string $teamId): array
    {
        return $this->request('POST', "/team/{$teamId}/time_entries/stop");
    }

    /**
     * Add a manual time entry.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function addTimeEntry(string $teamId, array $data): array
    {
        return $this->request('POST', "/team/{$teamId}/time_entries", $data);
    }

    /**
     * Get the currently running time entry.
     *
     * @return array<string, mixed>
     */
    public function getCurrentTimeEntry(string $teamId): array
    {
        return $this->request('GET', "/team/{$teamId}/time_entries/current");
    }

    // ── Lists ──────────────────────────────────────────────

    /**
     * Get a list.
     *
     * @return array<string, mixed>
     */
    public function getList(string $listId): array
    {
        return $this->request('GET', "/list/{$listId}");
    }

    /**
     * Create a list in a space.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createList(string $spaceId, array $data): array
    {
        return $this->request('POST', "/space/{$spaceId}/list", $data);
    }

    /**
     * Create a list in a folder.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createListInFolder(string $folderId, array $data): array
    {
        return $this->request('POST', "/folder/{$folderId}/list", $data);
    }

    /**
     * Update a list.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updateList(string $listId, array $data): array
    {
        return $this->request('PUT', "/list/{$listId}", $data);
    }

    // ── Folders ────────────────────────────────────────────

    /**
     * Get a folder.
     *
     * @return array<string, mixed>
     */
    public function getFolder(string $folderId): array
    {
        return $this->request('GET', "/folder/{$folderId}");
    }

    /**
     * Create a folder in a space.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createFolder(string $spaceId, array $data): array
    {
        return $this->request('POST', "/space/{$spaceId}/folder", $data);
    }

    /**
     * Update a folder.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updateFolder(string $folderId, array $data): array
    {
        return $this->request('PUT', "/folder/{$folderId}", $data);
    }

    // ── Chat ───────────────────────────────────────────────

    /**
     * Get chat channels. Uses v3 API.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function getChatChannels(string $teamId, array $params = []): array
    {
        return $this->requestV3('GET', "/workspaces/{$teamId}/chat/channels", $params);
    }

    /**
     * Send a chat message. Uses v3 API.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function sendChatMessage(string $teamId, string $channelId, array $data): array
    {
        return $this->requestV3('POST', "/workspaces/{$teamId}/chat/channels/{$channelId}/messages", $data);
    }

    // ── Documents ──────────────────────────────────────────

    /**
     * Create a document. Uses v3 API.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createDocument(string $workspaceId, array $data): array
    {
        return $this->requestV3('POST', "/workspaces/{$workspaceId}/docs", $data);
    }

    /**
     * List document pages. Uses v3 API.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function listDocumentPages(string $workspaceId, string $docId, array $params = []): array
    {
        return $this->requestV3('GET', "/workspaces/{$workspaceId}/docs/{$docId}/pages", $params);
    }

    /**
     * Get document page content. Uses v3 API.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function getDocumentPages(string $workspaceId, string $docId, array $params = []): array
    {
        return $this->requestV3('GET', "/workspaces/{$workspaceId}/docs/{$docId}/pages", $params);
    }

    /**
     * Create a document page. Uses v3 API.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createDocumentPage(string $workspaceId, string $docId, array $data): array
    {
        return $this->requestV3('POST', "/workspaces/{$workspaceId}/docs/{$docId}/pages", $data);
    }

    /**
     * Update a document page. Uses v3 API.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updateDocumentPage(string $workspaceId, string $docId, string $pageId, array $data): array
    {
        return $this->requestV3('PUT', "/workspaces/{$workspaceId}/docs/{$docId}/pages/{$pageId}", $data);
    }

    // ── Helpers ─────────────────────────────────────────────

    /**
     * Detect whether a task ID is a custom ID (e.g., "DEV-42").
     */
    public function isCustomTaskId(string $taskId): bool
    {
        return (bool) preg_match('/^[A-Z]+-\d+$/', $taskId);
    }

    /**
     * Build query params for custom task IDs.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function withCustomIdParams(string $taskId, array $params = []): array
    {
        if ($this->isCustomTaskId($taskId) && $this->workspaceId) {
            $params['custom_task_ids'] = 'true';
            $params['team_id'] = $this->workspaceId;
        }

        return $params;
    }

    /**
     * Convert an ISO 8601 date string to millisecond timestamp.
     */
    public static function toMillis(string $date): int
    {
        return (int) (strtotime($date) * 1000);
    }

    /**
     * Convert a millisecond timestamp to ISO 8601 string.
     */
    public static function fromMillis(int $millis): string
    {
        return date('Y-m-d H:i:s', (int) ($millis / 1000));
    }

    // ── HTTP ───────────────────────────────────────────────

    /**
     * Make an API v2 request.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, array $data = []): array
    {
        return $this->doRequest($method, self::BASE_URL . $path, $data);
    }

    /**
     * Make an API v3 request.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function requestV3(string $method, string $path, array $data = []): array
    {
        return $this->doRequest($method, 'https://api.clickup.com/api/v3' . $path, $data);
    }

    /**
     * Execute an HTTP request.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function doRequest(string $method, string $url, array $data = []): array
    {
        if (! $this->apiToken) {
            throw new \RuntimeException('ClickUp API token is not configured.');
        }

        try {
            $http = Http::withHeaders([
                'Authorization' => $this->apiToken,
                'Content-Type' => 'application/json',
            ])->timeout(30);

            $response = match (strtoupper($method)) {
                'GET' => $http->get($url, $data),
                'POST' => $http->post($url, $data),
                'PUT' => $http->put($url, $data),
                'DELETE' => $http->delete($url, $data),
                default => throw new \RuntimeException("Unsupported HTTP method: {$method}"),
            };

            if (! $response->successful()) {
                $body = $response->json() ?? [];
                $err = $body['err'] ?? $body['error'] ?? $response->body();
                $ecode = $body['ECODE'] ?? '';

                Log::error("ClickUp API error: {$method} {$url}", [
                    'status' => $response->status(),
                    'err' => $err,
                    'ECODE' => $ecode,
                ]);

                $msg = is_string($err) ? $err : json_encode($err);
                if ($ecode) {
                    $msg .= " (code: {$ecode})";
                }

                throw new \RuntimeException('ClickUp API error (' . $response->status() . '): ' . $msg);
            }

            return $response->json() ?? [];
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error("ClickUp API connection error: {$method} {$url}", [
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException("Failed to connect to ClickUp API: {$e->getMessage()}");
        }
    }
}
