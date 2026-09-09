<?php

namespace Plugitify\Services\Admin;

class SettingsService
{
	public const OPTION_KEY = 'plugitify_ai_settings';

	/**
	 * @return array<string, array{label: string, group: string, models: array<string, string>}>
	 */
	public static function providers(): array
	{
		$openaiModels = [
			'gpt-6-astra'   => 'GPT-6 Astra',
			'gpt-5.6-sol'   => 'GPT-5.6 Sol',
			'gpt-5.6-terra' => 'GPT-5.6 Terra',
			'gpt-5.6-luna'  => 'GPT-5.6 Luna',
			'gpt-5.5'       => 'GPT-5.5',
			'gpt-5.4-pro'   => 'GPT-5.4 Pro',
			'gpt-5.4'       => 'GPT-5.4',
			'gpt-5.4-mini'  => 'GPT-5.4 Mini',
			'gpt-5.4-nano'  => 'GPT-5.4 Nano',
		];

		$claudeModels = [
			'claude-fable-5-1' => 'Claude Fable 5.1',
			'claude-fable-5'   => 'Claude Fable 5',
			'claude-opus-5'    => 'Claude Opus 5',
			'claude-sonnet-5'  => 'Claude Sonnet 5',
			'claude-haiku-4-5' => 'Claude Haiku 4.5',
		];

		$geminiModels = [
			'gemini-3.8-flash'       => 'Gemini 3.8 Flash',
			'gemini-3.7-flash'       => 'Gemini 3.7 Flash',
			'gemini-3.6-flash'       => 'Gemini 3.6 Flash',
			'gemini-3.5-flash'       => 'Gemini 3.5 Flash',
			'gemini-3.5-flash-lite'  => 'Gemini 3.5 Flash-Lite',
			'gemini-3.1-pro-preview' => 'Gemini 3.1 Pro',
		];

		$deepseekModels = [
			'deepseek-v4-pro'   => 'DeepSeek V4 Pro',
			'deepseek-v4-flash' => 'DeepSeek V4 Flash',
			'deepseek-v3.2'     => 'DeepSeek V3.2',
			'deepseek-chat'     => 'DeepSeek Chat',
		];

		$qwenModels = [
			'qwen3.8-max'   => 'Qwen3.8 Max',
			'qwen3.8-flash' => 'Qwen3.8 Flash',
			'qwen3.7-max'   => 'Qwen3.7 Max',
			'qwen3.7-plus'  => 'Qwen3.7 Plus',
		];

		$zaiModels = [
			'glm-5.3'       => 'GLM-5.3',
			'glm-5.3-flash' => 'GLM-5.3 Flash',
			'glm-5.2'       => 'GLM-5.2',
			'glm-5.1'       => 'GLM-5.1',
			'glm-5'         => 'GLM-5',
		];

		$aggregatorModels = array_merge(
			$openaiModels,
			$claudeModels,
			$geminiModels,
			$deepseekModels,
			$qwenModels,
			$zaiModels
		);

		return [
			'openai' => [
				'label'  => 'OpenAI',
				'group'  => 'global',
				'models' => $openaiModels,
			],
			'claude' => [
				'label'  => 'Claude (Anthropic)',
				'group'  => 'global',
				'models' => $claudeModels,
			],
			'gemini' => [
				'label'  => 'Gemini (Google)',
				'group'  => 'global',
				'models' => $geminiModels,
			],
			'deepseek' => [
				'label'  => 'DeepSeek',
				'group'  => 'global',
				'models' => $deepseekModels,
			],
			'qwen' => [
				'label'  => 'Qwen (Alibaba)',
				'group'  => 'global',
				'models' => $qwenModels,
			],
			'zai' => [
				'label'  => 'Z.ai (GLM)',
				'group'  => 'global',
				'models' => $zaiModels,
			],
			'gapgpt' => [
				'label'  => 'GapGPT',
				'group'  => 'iran',
				'models' => $aggregatorModels,
			],
			'avalai' => [
				'label'  => 'AvalAI',
				'group'  => 'iran',
				'models' => $aggregatorModels,
			],
		];
	}

	/**
	 * @return array{provider: string, model: string, api_key: string}
	 */
	public static function getSettings(): array
	{
		$stored = get_option( self::OPTION_KEY, [] );

		if ( ! is_array( $stored ) ) {
			$stored = [];
		}

		$providers = self::providers();
		$provider  = isset( $stored['provider'] ) ? (string) $stored['provider'] : 'openai';

		if ( ! isset( $providers[ $provider ] ) ) {
			$provider = 'openai';
		}

		$modelKeys = array_keys( $providers[ $provider ]['models'] );
		$model     = isset( $stored['model'] ) ? (string) $stored['model'] : (string) ( $modelKeys[0] ?? '' );

		if ( ! isset( $providers[ $provider ]['models'][ $model ] ) ) {
			$model = (string) ( $modelKeys[0] ?? '' );
		}

		return [
			'provider' => $provider,
			'model'    => $model,
			'api_key'  => isset( $stored['api_key'] ) ? (string) $stored['api_key'] : '',
		];
	}

	public function handleSave(): void
	{
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! isset( $_POST['plugitify_settings_nonce'] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( (string) $_POST['plugitify_settings_nonce'] ) );

		if ( ! wp_verify_nonce( $nonce, 'plugitify_save_settings' ) ) {
			return;
		}

		$providers = self::providers();
		$provider  = isset( $_POST['provider'] ) ? sanitize_key( wp_unslash( (string) $_POST['provider'] ) ) : '';

		if ( ! isset( $providers[ $provider ] ) ) {
			$provider = 'openai';
		}

		$model = isset( $_POST['model'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['model'] ) ) : '';

		if ( ! isset( $providers[ $provider ]['models'][ $model ] ) ) {
			$modelKeys = array_keys( $providers[ $provider ]['models'] );
			$model     = (string) ( $modelKeys[0] ?? '' );
		}

		$apiKey = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['api_key'] ) ) : '';

		update_option(
			self::OPTION_KEY,
			[
				'provider' => $provider,
				'model'    => $model,
				'api_key'  => $apiKey,
			],
			false
		);

		set_transient( 'plugitify_settings_saved', 1, 30 );

		wp_safe_redirect(
			add_query_arg(
				[ 'page' => 'plugitify-settings', 'updated' => '1' ],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public function render(): void
	{
		$settings  = self::getSettings();
		$providers = self::providers();
		$saved     = isset( $_GET['updated'] ) || get_transient( 'plugitify_settings_saved' );

		if ( get_transient( 'plugitify_settings_saved' ) ) {
			delete_transient( 'plugitify_settings_saved' );
		}

		include PLUGITIFY_PATH . 'src/Views/Admin/settings.php';
	}
}
