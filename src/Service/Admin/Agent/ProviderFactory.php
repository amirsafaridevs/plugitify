<?php

namespace Plugifity\Service\Admin\Agent;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\Anthropic\Anthropic;
use NeuronAI\Providers\Deepseek\Deepseek;
use NeuronAI\Providers\Gemini\Gemini;
use NeuronAI\Providers\OpenAI\Responses\OpenAIResponses;
use Plugifity\Service\Admin\Agent\CustomGuzzleHttpClient;
use Plugifity\Service\Admin\Settings;

/**
 * Builds NeuronAI provider from plugin Settings (model + per-provider API keys).
 * Maps provider|model value to the correct NeuronAI provider class.
 */
class ProviderFactory
{
    /**
     * Build AI provider from current Settings.
     *
     * @param array{model: string, apiKeys: array<string, string>} $settings From Settings::get().
     * @return AIProviderInterface
     */
    public static function build( array $settings ): AIProviderInterface
    {
        $model   = $settings['model'] ?? Settings::DEFAULT_MODEL;
        $apiKeys = $settings['apiKeys'] ?? [];
        $parts   = explode( '|', $model, 2 );
        $provider = $parts[0] ?? 'deepseek';
        $modelId  = $parts[1] ?? 'deepseek-chat';
        $key      = $apiKeys[ $provider ] ?? '';

        $httpClient = new CustomGuzzleHttpClient( verifySSL: false );

        return match ( $provider ) {
            'deepseek' => new Deepseek(
                key: $key,
                model: $modelId,
                parameters: [],
                strict_response: false,
                httpClient: $httpClient
            ),
            'chatgpt' => new OpenAIResponses(
                key: $key,
                model: $modelId,
                parameters: [],
                strict_response: false,
                httpClient: $httpClient
            ),
            'gemini' => new Gemini(
                key: $key,
                model: $modelId,
                parameters: [],
                httpClient: $httpClient
            ),
            'claude' => new Anthropic(
                key: $key,
                model: $modelId,
                parameters: [],
                httpClient: $httpClient
            ),
            default => new Deepseek(
                key: $key,
                model: $modelId,
                parameters: [],
                strict_response: false,
                httpClient: $httpClient
            ),
        };
    }

    /**
     * Build AI provider from current stored options (convenience).
     *
     * @return AIProviderInterface
     */
    public static function buildFromSettings(): AIProviderInterface
    {
        return self::build( Settings::get() );
    }
}
