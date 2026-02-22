<?php

namespace OpenCompany\AiToolClickUp;

use Illuminate\Support\Facades\Http;
use Laravel\Ai\Contracts\Tool;
use OpenCompany\AiToolClickUp\Tools\ClickUpAddComment;
use OpenCompany\AiToolClickUp\Tools\ClickUpAddTag;
use OpenCompany\AiToolClickUp\Tools\ClickUpAttachFile;
use OpenCompany\AiToolClickUp\Tools\ClickUpCreateDocPage;
use OpenCompany\AiToolClickUp\Tools\ClickUpCreateFolder;
use OpenCompany\AiToolClickUp\Tools\ClickUpCreateList;
use OpenCompany\AiToolClickUp\Tools\ClickUpCreateListInFolder;
use OpenCompany\AiToolClickUp\Tools\ClickUpCreateTask;
use OpenCompany\AiToolClickUp\Tools\ClickUpCurrentTimeEntry;
use OpenCompany\AiToolClickUp\Tools\ClickUpDeleteTask;
use OpenCompany\AiToolClickUp\Tools\ClickUpFindMember;
use OpenCompany\AiToolClickUp\Tools\ClickUpGetDocPages;
use OpenCompany\AiToolClickUp\Tools\ClickUpGetFolder;
use OpenCompany\AiToolClickUp\Tools\ClickUpGetHierarchy;
use OpenCompany\AiToolClickUp\Tools\ClickUpGetList;
use OpenCompany\AiToolClickUp\Tools\ClickUpGetTask;
use OpenCompany\AiToolClickUp\Tools\ClickUpGetTasks;
use OpenCompany\AiToolClickUp\Tools\ClickUpListChannels;
use OpenCompany\AiToolClickUp\Tools\ClickUpListDocPages;
use OpenCompany\AiToolClickUp\Tools\ClickUpListMembers;
use OpenCompany\AiToolClickUp\Tools\ClickUpListTimeEntries;
use OpenCompany\AiToolClickUp\Tools\ClickUpLogTime;
use OpenCompany\AiToolClickUp\Tools\ClickUpManageDocument;
use OpenCompany\AiToolClickUp\Tools\ClickUpReadComments;
use OpenCompany\AiToolClickUp\Tools\ClickUpRemoveTag;
use OpenCompany\AiToolClickUp\Tools\ClickUpResolveMembers;
use OpenCompany\AiToolClickUp\Tools\ClickUpSearch;
use OpenCompany\AiToolClickUp\Tools\ClickUpSendMessage;
use OpenCompany\AiToolClickUp\Tools\ClickUpStartTimer;
use OpenCompany\AiToolClickUp\Tools\ClickUpStopTimer;
use OpenCompany\AiToolClickUp\Tools\ClickUpUpdateDocPage;
use OpenCompany\AiToolClickUp\Tools\ClickUpUpdateFolder;
use OpenCompany\AiToolClickUp\Tools\ClickUpUpdateList;
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
            // Members
            'clickup_list_members' => [
                'class' => ClickUpListMembers::class,
                'type' => 'read',
                'name' => 'List Members',
                'description' => 'Get all workspace members.',
                'icon' => 'ph:users',
            ],
            'clickup_find_member' => [
                'class' => ClickUpFindMember::class,
                'type' => 'read',
                'name' => 'Find Member',
                'description' => 'Find a member by name or email.',
                'icon' => 'ph:user',
            ],
            'clickup_resolve_members' => [
                'class' => ClickUpResolveMembers::class,
                'type' => 'read',
                'name' => 'Resolve Members',
                'description' => 'Convert names/emails to user IDs.',
                'icon' => 'ph:user-switch',
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
            // Tags
            'clickup_add_tag' => [
                'class' => ClickUpAddTag::class,
                'type' => 'write',
                'name' => 'Add Tag',
                'description' => 'Add an existing tag to a task.',
                'icon' => 'ph:tag',
            ],
            'clickup_remove_tag' => [
                'class' => ClickUpRemoveTag::class,
                'type' => 'write',
                'name' => 'Remove Tag',
                'description' => 'Remove a tag from a task.',
                'icon' => 'ph:tag',
            ],
            'clickup_attach_file' => [
                'class' => ClickUpAttachFile::class,
                'type' => 'write',
                'name' => 'Attach File',
                'description' => 'Attach a file to a task.',
                'icon' => 'ph:paperclip',
            ],
            // Comments
            'clickup_read_comments' => [
                'class' => ClickUpReadComments::class,
                'type' => 'read',
                'name' => 'Read Comments',
                'description' => 'Get all comments on a task.',
                'icon' => 'ph:chat-circle',
            ],
            'clickup_add_comment' => [
                'class' => ClickUpAddComment::class,
                'type' => 'write',
                'name' => 'Add Comment',
                'description' => 'Add a comment to a task.',
                'icon' => 'ph:chat-circle',
            ],
            // Time Tracking
            'clickup_start_timer' => [
                'class' => ClickUpStartTimer::class,
                'type' => 'write',
                'name' => 'Start Timer',
                'description' => 'Start a timer on a task.',
                'icon' => 'ph:timer',
            ],
            'clickup_stop_timer' => [
                'class' => ClickUpStopTimer::class,
                'type' => 'write',
                'name' => 'Stop Timer',
                'description' => 'Stop the running timer.',
                'icon' => 'ph:timer',
            ],
            'clickup_log_time' => [
                'class' => ClickUpLogTime::class,
                'type' => 'write',
                'name' => 'Log Time',
                'description' => 'Add a manual time entry.',
                'icon' => 'ph:timer',
            ],
            'clickup_list_time_entries' => [
                'class' => ClickUpListTimeEntries::class,
                'type' => 'read',
                'name' => 'List Time Entries',
                'description' => 'Get all time entries for a task.',
                'icon' => 'ph:timer',
            ],
            'clickup_current_time_entry' => [
                'class' => ClickUpCurrentTimeEntry::class,
                'type' => 'read',
                'name' => 'Current Time Entry',
                'description' => 'Get the currently running timer.',
                'icon' => 'ph:timer',
            ],
            // Lists
            'clickup_create_list' => [
                'class' => ClickUpCreateList::class,
                'type' => 'write',
                'name' => 'Create List',
                'description' => 'Create a list in a space.',
                'icon' => 'ph:list-bullets',
            ],
            'clickup_create_list_in_folder' => [
                'class' => ClickUpCreateListInFolder::class,
                'type' => 'write',
                'name' => 'Create List in Folder',
                'description' => 'Create a list in a folder.',
                'icon' => 'ph:list-bullets',
            ],
            'clickup_get_list' => [
                'class' => ClickUpGetList::class,
                'type' => 'read',
                'name' => 'Get List',
                'description' => 'Get list details by ID.',
                'icon' => 'ph:list-bullets',
            ],
            'clickup_update_list' => [
                'class' => ClickUpUpdateList::class,
                'type' => 'write',
                'name' => 'Update List',
                'description' => 'Update a list.',
                'icon' => 'ph:list-bullets',
            ],
            // Folders
            'clickup_create_folder' => [
                'class' => ClickUpCreateFolder::class,
                'type' => 'write',
                'name' => 'Create Folder',
                'description' => 'Create a folder in a space.',
                'icon' => 'ph:folder',
            ],
            'clickup_get_folder' => [
                'class' => ClickUpGetFolder::class,
                'type' => 'read',
                'name' => 'Get Folder',
                'description' => 'Get folder details by ID.',
                'icon' => 'ph:folder',
            ],
            'clickup_update_folder' => [
                'class' => ClickUpUpdateFolder::class,
                'type' => 'write',
                'name' => 'Update Folder',
                'description' => 'Update a folder.',
                'icon' => 'ph:folder',
            ],
            // Chat
            'clickup_list_channels' => [
                'class' => ClickUpListChannels::class,
                'type' => 'read',
                'name' => 'List Channels',
                'description' => 'List all chat channels.',
                'icon' => 'ph:chat-dots',
            ],
            'clickup_send_message' => [
                'class' => ClickUpSendMessage::class,
                'type' => 'write',
                'name' => 'Send Message',
                'description' => 'Send a message to a chat channel.',
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
            'clickup_list_doc_pages' => [
                'class' => ClickUpListDocPages::class,
                'type' => 'read',
                'name' => 'List Doc Pages',
                'description' => 'List all pages in a document.',
                'icon' => 'ph:note',
            ],
            'clickup_get_doc_pages' => [
                'class' => ClickUpGetDocPages::class,
                'type' => 'read',
                'name' => 'Get Doc Pages',
                'description' => 'Get the content of document pages.',
                'icon' => 'ph:note',
            ],
            'clickup_create_doc_page' => [
                'class' => ClickUpCreateDocPage::class,
                'type' => 'write',
                'name' => 'Create Doc Page',
                'description' => 'Create a new page in a document.',
                'icon' => 'ph:note',
            ],
            'clickup_update_doc_page' => [
                'class' => ClickUpUpdateDocPage::class,
                'type' => 'write',
                'name' => 'Update Doc Page',
                'description' => 'Update a page in a document.',
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
