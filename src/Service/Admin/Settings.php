<?php

namespace Plugifity\Service\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Assistant settings (model + per-provider API keys).
 * Stored in WordPress options with plugin prefix; each provider key is separate so changing provider does not clear others.
 */
class Settings
{
    public const OPTION_PREFIX = 'plugifity_';

    public const OPTION_MODEL = self::OPTION_PREFIX . 'model';
    public const OPTION_API_KEY_DEEPSEEK  = self::OPTION_PREFIX . 'api_key_deepseek';
    public const OPTION_API_KEY_CHATGPT   = self::OPTION_PREFIX . 'api_key_chatgpt';
    public const OPTION_API_KEY_GEMINI    = self::OPTION_PREFIX . 'api_key_gemini';
    public const OPTION_API_KEY_CLAUDE    = self::OPTION_PREFIX . 'api_key_claude';
    public const OPTION_ALLOW_DB_WRITES   = self::OPTION_PREFIX . 'allow_db_writes';

    public const DEFAULT_MODEL = 'deepseek|deepseek-chat';

    /**
     * Option keys per provider (for iteration).
     *
     * @var array<string, string>
     */
    public static function getApiKeyOptionKeys(): array
    {
        return [
            'deepseek' => self::OPTION_API_KEY_DEEPSEEK,
            'chatgpt'  => self::OPTION_API_KEY_CHATGPT,
            'gemini'   => self::OPTION_API_KEY_GEMINI,
            'claude'   => self::OPTION_API_KEY_CLAUDE,
        ];
    }

    /**
     * Get current settings (model + api keys per provider).
     *
     * @return array{model: string, apiKeys: array<string, string>}
     */
    public static function get(): array
    {
        $model = get_option( self::OPTION_MODEL, self::DEFAULT_MODEL );
        $model = is_string( $model ) ? $model : self::DEFAULT_MODEL;

        $apiKeys = [];
        foreach ( self::getApiKeyOptionKeys() as $provider => $optionKey ) {
            $val = get_option( $optionKey, '' );
            $apiKeys[ $provider ] = is_string( $val ) ? $val : '';
        }

        $allowDbWrites = (bool) get_option( self::OPTION_ALLOW_DB_WRITES, false );

        return [
            'model'          => $model,
            'apiKeys'        => $apiKeys,
            'allowDbWrites'  => $allowDbWrites,
        ];
    }

    /**
     * Save model + per-provider API keys + allow DB writes.
     *
     * @param string $model Value like "deepseek|deepseek-chat".
     * @param array<string, string> $apiKeys Keys: deepseek, chatgpt, gemini, claude.
     * @param bool $allowDbWrites Whether the agent may run INSERT/UPDATE/DELETE (admin permission).
     * @return bool
     */
    public static function save( string $model, array $apiKeys, bool $allowDbWrites = false ): bool
    {
        $model = sanitize_text_field( $model );
        if ( $model === '' ) {
            $model = self::DEFAULT_MODEL;
        }
        update_option( self::OPTION_MODEL, $model );

        foreach ( self::getApiKeyOptionKeys() as $provider => $optionKey ) {
            $value = isset( $apiKeys[ $provider ] ) && is_string( $apiKeys[ $provider ] )
                ? sanitize_text_field( $apiKeys[ $provider ] )
                : '';
            update_option( $optionKey, $value );
        }

        update_option( self::OPTION_ALLOW_DB_WRITES, $allowDbWrites ? '1' : '0' );

        return true;
    }
}
