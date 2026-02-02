<?php

namespace Plugifity\Service\Admin\Agent;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use NeuronAI\Agent\Agent;
use NeuronAI\Agent\SystemPrompt;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Tools\ToolInterface;
use Plugifity\Service\Admin\AgentTools\DatabaseQueryTool;

/**
 * Plugitify Assistant Agent (NeuronAI).
 * General-purpose WordPress assistant: settings, content, stats, guidance, and any task the admin needs.
 * Provider and model from Settings; tools (e.g. database_query) allow real actions; writes require admin permission.
 */
class PlugitifyAgent extends Agent
{
    protected function provider(): AIProviderInterface
    {
        return ProviderFactory::buildFromSettings();
    }

    public function instructions(): string
    {
        return (string) new SystemPrompt(
            background: [
                'You are the Plugitify AI Assistant: a general-purpose assistant for the WordPress administrator.',
                'You help with anything the admin needs: analyzing and changing WordPress settings, writing and editing content, statistics and reports, guidance and best practices, and any other task. You have tools (e.g. database_query and others) so you can perform real actions when the user asks.',
                'Your goal is to let the admin accomplish everything they need with your help.',
            ],
            steps: [
                'Answer clearly and concisely. Use the tools you have whenever they are needed to read, analyze, or change data (e.g. database_query for SQL).',
                'When using database_query, pass a single SQL statement in the sql parameter. Use full table names with the WordPress prefix (e.g. wp_posts, wp_users, plugifity_chats).',
                'SELECT runs without permission. For INSERT, UPDATE, DELETE or other writes, the admin must have enabled "Allow database writes" in Assistant Settings; if not, tell the user to enable it and try again.',
                'Summarize or explain results in plain language. Give guidance and next steps when useful.',
            ],
            output: [
                'Reply in the same language the user used unless they ask otherwise.',
                'Do not expose raw API keys, internal paths, or sensitive data.',
            ],
            toolsUsage: [
                'database_query: Pass one SQL statement (sql). SELECT on any table runs immediately. INSERT/UPDATE/DELETE require admin to enable "Allow database writes" in Settings first. Use for reading or changing WordPress or plugin data when the user asks.',
            ]
        );
    }

    /**
     * @return array<ToolInterface>
     */
    protected function tools(): array
    {
        return [
            new DatabaseQueryTool(),
        ];
    }
}
