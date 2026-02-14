<?php

namespace OpenCompany\AiToolClickUp;

use Illuminate\Support\Facades\Http;
use Laravel\Ai\Contracts\Tool;
use OpenCompany\AiToolClickUp\Tools\ClickUpAttachFile;
use OpenCompany\AiToolClickUp\Tools\ClickUpChat;
use OpenCompany\AiToolClickUp\Tools\ClickUpCreateTask;
use OpenCompany\AiToolClickUp\Tools\ClickUpDeleteTask;
use OpenCompany\AiToolClickUp\Tools\ClickUpGetHierarchy;
use OpenCompany\AiToolClickUp\Tools\ClickUpGetTask;
use OpenCompany\AiToolClickUp\Tools\ClickUpGetTasks;
use OpenCompany\AiToolClickUp\Tools\ClickUpManageComments;
use OpenCompany\AiToolClickUp\Tools\ClickUpManageDocument;
use OpenCompany\AiToolClickUp\Tools\ClickUpManageDocumentPages;
use OpenCompany\AiToolClickUp\Tools\ClickUpManageFolder;
use OpenCompany\AiToolClickUp\Tools\ClickUpManageList;
use OpenCompany\AiToolClickUp\Tools\ClickUpManageTags;
use OpenCompany\AiToolClickUp\Tools\ClickUpMembers;
use OpenCompany\AiToolClickUp\Tools\ClickUpSearch;
use OpenCompany\AiToolClickUp\Tools\ClickUpTimeTracking;
use OpenCompany\AiToolClickUp\Tools\ClickUpUpdateTask;
use OpenCompany\IntegrationCore\Contracts\ConfigurableIntegration;
use OpenCompany\IntegrationCore\Contracts\ToolProvider;

class ClickUpToolProvider implements ToolProvider, ConfigurableIntegration
{
    public function appName(): string
    {
        return 'clickup';
    }

    public function appMeta(): array
    {
        return [
            'label' => 'tasks, projects, docs, time tracking',
            'description' => 'Project management',
            'icon' => 'ph:kanban',
            'logo' => 'simple-icons:clickup',
        ];
    }

    public function integrationMeta(): array
    {
        return [
            'name' => 'ClickUp',
            'description' => 'Project management, tasks, docs, and time tracking',
            'icon' => 'ph:kanban',
            'logo' => 'simple-icons:clickup',
            'category' => 'productivity',
            'badge' => 'verified',
            'docs_url' => 'https://clickup.com/api',
        ];
    }

    public function configSchema(): array
    {
        return [
            [
                'key' => 'api_token',
                'type' => 'secret',
                'label' => 'Personal API Token',
                'placeholder' => 'pk_...',
                'hint' => 'Generate at ClickUp → Settings → Apps. Starts with <code>pk_</code>.',
                'required' => true,
            ],
            [
                'key' => 'workspace_id',
                'type' => 'text',
                'label' => 'Workspace ID',
                'placeholder' => '12345678',
                'hint' => 'From your ClickUp URL: <code>app.clickup.com/{workspace_id}/...</code>. Required for search, time tracking, and members.',
            ],
        ];
    }

    public function testConnection(array $config): array
    {
        $apiToken = $config['api_token'] ?? '';

        if (empty($apiToken)) {
            return ['success' => false, 'error' => 'No API token provided. Generate one at ClickUp → Settings → Apps.'];
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => $apiToken,
                'Content-Type' => 'application/json',
            ])->timeout(10)->get('https://api.clickup.com/api/v2/team');

            if ($response->successful()) {
                $teams = $response->json('teams') ?? [];
                $names = array_map(fn (array $t) => $t['name'] ?? 'Unknown', $teams);
                $count = count($teams);

                return [
                    'success' => true,
                    'message' => "Connected to ClickUp. Found {$count} workspace(s): " . implode(', ', $names),
                ];
            }

            $error = $response->json('err') ?? $response->body();

            return [
                'success' => false,
                'error' => 'ClickUp API error (' . $response->status() . '): ' . (is_string($error) ? $error : json_encode($error)),
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /** @return array<string, string|array<int, string>> */
    public function validationRules(): array
    {
        return [
            'api_token' => 'nullable|string',
            'workspace_id' => 'nullable|string',
        ];
    }

    public function tools(): array
    {
        return [
            // Navigation & Workspace
            'clickup_get_hierarchy' => [
                'class' => ClickUpGetHierarchy::class,
                'type' => 'read',
                'name' => 'Get Hierarchy',
                'description' => 'Get workspace hierarchy: spaces, folders, lists.',
                'icon' => 'ph:tree-structure',
            ],
            'clickup_search' => [
                'class' => ClickUpSearch::class,
                'type' => 'read',
                'name' => 'Search Tasks',
                'description' => 'Search tasks across the workspace.',
                'icon' => 'ph:magnifying-glass',
            ],
            'clickup_members' => [
                'class' => ClickUpMembers::class,
                'type' => 'read',
                'name' => 'Members',
                'description' => 'List, find, or resolve workspace members.',
                'icon' => 'ph:users',
            ],
            // Task Management
            'clickup_get_tasks' => [
                'class' => ClickUpGetTasks::class,
                'type' => 'read',
                'name' => 'Get Tasks',
                'description' => 'Get all tasks in a list.',
                'icon' => 'ph:list-checks',
            ],
            'clickup_get_task' => [
                'class' => ClickUpGetTask::class,
                'type' => 'read',
                'name' => 'Get Task',
                'description' => 'Get a task by ID with details.',
                'icon' => 'ph:clipboard-text',
            ],
            'clickup_create_task' => [
                'class' => ClickUpCreateTask::class,
                'type' => 'write',
                'name' => 'Create Task',
                'description' => 'Create a new task in a list.',
                'icon' => 'ph:plus-circle',
            ],
            'clickup_update_task' => [
                'class' => ClickUpUpdateTask::class,
                'type' => 'write',
                'name' => 'Update Task',
                'description' => 'Update an existing task.',
                'icon' => 'ph:pencil-simple',
            ],
            'clickup_delete_task' => [
                'class' => ClickUpDeleteTask::class,
                'type' => 'write',
                'name' => 'Delete Task',
                'description' => 'Delete a task.',
                'icon' => 'ph:trash',
            ],
            'clickup_manage_tags' => [
                'class' => ClickUpManageTags::class,
                'type' => 'write',
                'name' => 'Manage Tags',
                'description' => 'Add or remove tags on tasks.',
                'icon' => 'ph:tag',
            ],
            'clickup_attach_file' => [
                'class' => ClickUpAttachFile::class,
                'type' => 'write',
                'name' => 'Attach File',
                'description' => 'Attach a file to a task.',
                'icon' => 'ph:paperclip',
            ],
            // Collaboration
            'clickup_manage_comments' => [
                'class' => ClickUpManageComments::class,
                'type' => 'write',
                'name' => 'Comments',
                'description' => 'Read or add comments on tasks.',
                'icon' => 'ph:chat-circle',
            ],
            'clickup_time_tracking' => [
                'class' => ClickUpTimeTracking::class,
                'type' => 'write',
                'name' => 'Time Tracking',
                'description' => 'Start, stop, log, or list time entries.',
                'icon' => 'ph:timer',
            ],
            // Organization
            'clickup_manage_list' => [
                'class' => ClickUpManageList::class,
                'type' => 'write',
                'name' => 'Manage List',
                'description' => 'Create, get, or update lists.',
                'icon' => 'ph:list-bullets',
            ],
            'clickup_manage_folder' => [
                'class' => ClickUpManageFolder::class,
                'type' => 'write',
                'name' => 'Manage Folder',
                'description' => 'Create, get, or update folders.',
                'icon' => 'ph:folder',
            ],
            // Chat
            'clickup_chat' => [
                'class' => ClickUpChat::class,
                'type' => 'write',
                'name' => 'Chat',
                'description' => 'List channels or send messages.',
                'icon' => 'ph:chat-dots',
            ],
            // Documents
            'clickup_manage_document' => [
                'class' => ClickUpManageDocument::class,
                'type' => 'write',
                'name' => 'Manage Document',
                'description' => 'Create a ClickUp document.',
                'icon' => 'ph:file-text',
            ],
            'clickup_manage_document_pages' => [
                'class' => ClickUpManageDocumentPages::class,
                'type' => 'write',
                'name' => 'Document Pages',
                'description' => 'List, get, create, or update document pages.',
                'icon' => 'ph:note',
            ],
        ];
    }

    public function isIntegration(): bool
    {
        return true;
    }

    /** @param  array<string, mixed>  $context */
    public function createTool(string $class, array $context = []): Tool
    {
        $service = app(ClickUpService::class);

        return new $class($service);
    }
}
