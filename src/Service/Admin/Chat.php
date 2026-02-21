<?php

namespace Plugifity\Service\Admin;

if (!defined('ABSPATH')) {
    exit;
}

use Plugifity\Contract\Abstract\AbstractService;
use Plugifity\Core\Http\ApiRouter;
use Plugifity\Core\Http\Request;
use Plugifity\Core\Settings as CoreSettings;
use Plugifity\Repository\ChatRepository;
use Plugifity\Repository\LogRepository;
use Plugifity\Repository\MessageRepository;

/**
 * Admin Chat service (uses ChatRepository when bound).
 */
class Chat extends AbstractService
{
    public const SUBMENU_SLUG = 'plugifity-chat';

    /**
     * Boot the service.
     * Use $this->getContainer() when you need the container.
     *
     * @return void
     */
    public function boot(): void
    {
        add_action('admin_menu', [$this, 'registerMenu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueChatAssets']);
        $this->registerChatApiRoutes();
    }

    /**
     * Register REST API routes for chat (create, add message, update title).
     *
     * @return void
     */
    protected function registerChatApiRoutes(): void
    {
        $permission = function () {
            return current_user_can('manage_options');
        };

        ApiRouter::post('chat', [$this, 'apiCreateChat'])
            ->permission($permission)
            ->name('api.chat.create');

        ApiRouter::post('chat/{id}/messages', [$this, 'apiAddMessage'])
            ->permission($permission)
            ->name('api.chat.addMessage');

        ApiRouter::patch('chat/{id}', [$this, 'apiUpdateChatTitle'])
            ->permission($permission)
            ->name('api.chat.updateTitle');

        ApiRouter::get('chat/{id}/messages', [$this, 'apiGetMessages'])
            ->permission($permission)
            ->name('api.chat.getMessages');

        ApiRouter::get('chat/{id}/task-history', [$this, 'apiGetTaskHistory'])
            ->permission($permission)
            ->name('api.chat.getTaskHistory');

        ApiRouter::delete('chat/{id}', [$this, 'apiDeleteChat'])
            ->permission($permission)
            ->name('api.chat.delete');

        ApiRouter::post('chat/{id}/delete', [$this, 'apiDeleteChat'])
            ->permission($permission)
            ->name('api.chat.delete.post');
    }

    /**
     * POST /chat — Create a new chat, optionally with first user message.
     * Body: title (optional), first_message (optional).
     *
     * @param Request $request
     * @return array{id: int, title: string}
     */
    public function apiCreateChat(Request $request): array
    {
        $title = is_string($request->input('title')) ? trim($request->input('title')) : '';
        if ($title === '') {
            $title = __('New chat', 'plugitify');
        }
        $title = mb_substr($title, 0, 500);

        $chatRepo = $this->container->get(ChatRepository::class);
        $messageRepo = $this->container->get(MessageRepository::class);

        $chatId = $chatRepo->create(['title' => $title]);
        if ($chatId === false) {
            return ['error' => 'Failed to create chat', 'id' => 0, 'title' => $title];
        }

        $firstMessage = $request->input('first_message');
        if (is_string($firstMessage) && trim($firstMessage) !== '') {
            $messageRepo->create([
                'chat_id' => (int) $chatId,
                'role'    => 'user',
                'content' => trim($firstMessage),
            ]);
        }

        return ['id' => (int) $chatId, 'title' => $title];
    }

    /**
     * POST /chat/{id}/messages — Add a message to a chat.
     * Body: role (user|assistant), content.
     *
     * @param Request $request
     * @param string $id Chat ID from route
     * @return array{id: int}|array{error: string}
     */
    public function apiAddMessage(Request $request, string $id): array
    {
        $chatId = (int) $id;
        if ($chatId <= 0) {
            return ['error' => 'Invalid chat id'];
        }

        $role = is_string($request->input('role')) ? trim($request->input('role')) : '';
        if ($role !== 'user' && $role !== 'assistant') {
            return ['error' => 'Invalid role'];
        }
        $content = $request->input('content');
        $content = is_string($content) ? $content : '';

        $chatRepo = $this->container->get(ChatRepository::class);
        $chat = $chatRepo->find($chatId);
        if ($chat === null) {
            return ['error' => 'Chat not found'];
        }

        $messageRepo = $this->container->get(MessageRepository::class);
        $messageId = $messageRepo->create([
            'chat_id' => $chatId,
            'role'    => $role,
            'content' => $content,
        ]);
        if ($messageId === false) {
            return ['error' => 'Failed to save message'];
        }

        return ['id' => (int) $messageId];
    }

    /**
     * PATCH /chat/{id} — Update chat title.
     * Body: title.
     *
     * @param Request $request
     * @param string $id Chat ID from route
     * @return array{id: int, title: string}|array{error: string}
     */
    public function apiUpdateChatTitle(Request $request, string $id): array
    {
        $chatId = (int) $id;
        if ($chatId <= 0) {
            return ['error' => 'Invalid chat id'];
        }

        $title = is_string($request->input('title')) ? trim($request->input('title')) : '';
        if ($title === '') {
            return ['error' => 'Title is required', 'id' => $chatId];
        }
        $title = mb_substr($title, 0, 500);

        $chatRepo = $this->container->get(ChatRepository::class);
        $chat = $chatRepo->find($chatId);
        if ($chat === null) {
            return ['error' => 'Chat not found'];
        }

        $updated = $chatRepo->update($chatId, ['title' => $title]);
        return ['id' => $chatId, 'title' => $title, 'updated' => $updated !== false];
    }

    /**
     * GET /chat/{id}/messages — Get messages for a chat (oldest first).
     *
     * @param Request $request
     * @param string $id Chat ID from route
     * @return array{messages: array<int, array{id: int, role: string, content: string, created_at: string}>}|array{error: string}
     */
    public function apiGetMessages(Request $request, string $id): array
    {
        $chatId = (int) $id;
        if ($chatId <= 0) {
            return ['error' => 'Invalid chat id', 'messages' => []];
        }

        $chatRepo = $this->container->get(ChatRepository::class);
        $chat = $chatRepo->find($chatId);
        if ($chat === null) {
            return ['error' => 'Chat not found', 'messages' => []];
        }

        $messageRepo = $this->container->get(MessageRepository::class);
        $rows = $messageRepo->where('chat_id', $chatId)
            ->orderBy('created_at', 'ASC')
            ->get();

        $messages = [];
        foreach ($rows as $model) {
            $messages[] = [
                'id'         => (int) $model->id,
                'role'       => (string) ($model->role ?? ''),
                'content'    => (string) ($model->content ?? ''),
                'created_at' => (string) ($model->created_at ?? ''),
            ];
        }

        return ['messages' => $messages];
    }

    /**
     * GET /chat/{id}/task-history — Last 20 logs for this chat (for task_history in stream).
     *
     * @param Request $request
     * @param string $id Chat ID from route
     * @return array{task_history: list<string>}|array{error: string, task_history: array}
     */
    public function apiGetTaskHistory(Request $request, string $id): array
    {
        $chatId = (int) $id;
        if ($chatId <= 0) {
            return ['error' => 'Invalid chat id', 'task_history' => []];
        }

        $logRepo = $this->container->get(LogRepository::class);
        $taskHistory = $logRepo->getLastTaskHistoryForChat($chatId, 20);

        return ['task_history' => $taskHistory];
    }

    /**
     * DELETE /chat/{id} — Soft-delete a chat (set deleted_at).
     *
     * @param Request $request
     * @param string $id Chat ID from route
     * @return array{id: int, deleted: true}|array{error: string}
     */
    public function apiDeleteChat(Request $request, string $id): array
    {
        $chatId = (int) $id;
        if ($chatId <= 0) {
            return ['error' => 'Invalid chat id'];
        }

        $chatRepo = $this->container->get(ChatRepository::class);
        $chat = $chatRepo->find($chatId);
        if ($chat === null) {
            return ['error' => 'Chat not found'];
        }

        $now = current_time('mysql');
        $updated = $chatRepo->update($chatId, ['deleted_at' => $now]);
        return ['id' => $chatId, 'deleted' => true, 'updated' => $updated !== false];
    }

    /**
     * Enqueue chat assets only on Chat page.
     *
     * @param string $hook_suffix
     * @return void
     */
    public function enqueueChatAssets(string $hook_suffix): void
    {
        if ($hook_suffix !== 'plugifity_page_plugifity-chat') {
            return;
        }

        $app = $this->getApplication();
        // Reuse the Dashboard design system (variables, buttons, skeleton reveal).
        $app->enqueueStyle('plugitify-dashboard', 'admin/dashboard.css', [], 'admin_page:plugifity-chat');
        $app->enqueueScript('plugitify-dashboard', 'admin/dashboard.js', [], true, 'admin_page:plugifity-chat');

        // Chat-specific layout + interactions.
        $app->enqueueStyle('plugitify-chat', 'admin/chat.css', ['plugitify-dashboard'], 'admin_page:plugifity-chat');
        $app->enqueueScript('plugitify-chat', 'admin/chat.js', ['plugitify-dashboard'], true, 'admin_page:plugifity-chat');

        $backend_main_address = rtrim($app->getProperty('backend_main_address', ''), '/');
        $license_key = CoreSettings::get('license_key', '');
        $license_key = is_string($license_key) ? trim($license_key) : '';
        $has_license = $license_key !== '';

        $tools_api_token = CoreSettings::get('tools_api_token', '');
        $tools_api_token = is_string($tools_api_token) ? trim($tools_api_token) : '';

        $rest_base = rest_url('plugitify/v1/api');
        wp_localize_script('plugitify-chat', 'plugitifyChat', [
            'baseUrl'        => $backend_main_address,
            'siteUrl'       => rtrim(home_url(), '/'),
            'hasLicense'    => $has_license,
            'licenseKey'    => $license_key,
            'licenseMenuUrl' => admin_url('admin.php?page=plugifity-licence'),
            'toolsApiToken'  => $tools_api_token,
            'restUrl'       => rtrim($rest_base, '/'),
            'nonce'         => wp_create_nonce('wp_rest'),
        ]);
    }
    /**
     * Register the main Plugifity menu in admin.
     *
     * @return void
     */
    public function registerMenu(): void
    {
        add_submenu_page(
            'plugifity',
            __('Chat', 'plugitify'),
            __('Chat', 'plugitify'),
            'manage_options',
            self::SUBMENU_SLUG,
            [$this, 'renderChat'],
        );
    }
    /**
     * Render the chat page.
     *
     * @return void
     */
    public function renderChat(): void
    {
        $app = $this->getApplication();
        $chatRepo = $this->container->get(ChatRepository::class);
        $chats = $chatRepo->query()
            ->orderBy('updated_at', 'DESC')
            ->limit(100)
            ->get();
        $app->view('Chat/index', ['chats' => $chats]);
    }
}
