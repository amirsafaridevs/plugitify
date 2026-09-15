<?php
/**
 * @var array{provider: string, model: string, api_key: string} $settings
 * @var array<string, array{label: string, group: string, models: array<string, string>}> $providers
 * @var bool $saved
 * @var array{ready: bool, state: string, source: string, sourceReadable: bool, targetDir: string, target: string, filename: string} $muPluginStatus
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$currentModels = $providers[ $settings['provider'] ]['models'] ?? [];
?>
<div class="wrap">
	<div class="pty" id="pty-settings" dir="rtl">
		<header class="pty-hero">
			<div class="pty-hero__copy">
				<p class="pty-eyebrow"><?php esc_html_e( 'پلاگیتی', 'plugitify' ); ?></p>
				<h1><?php esc_html_e( 'تنظیمات', 'plugitify' ); ?></h1>
				<p class="pty-hero__lede">
					<?php esc_html_e( 'سرویس‌دهنده هوش مصنوعی، مدل و کلید API را تنظیم کنید تا استودیو بتواند درخواست‌ها را ارسال کند.', 'plugitify' ); ?>
				</p>
			</div>
			<button type="submit" class="pty-btn pty-btn--primary" form="plugitify-settings-form">
				<?php esc_html_e( 'ذخیره تنظیمات', 'plugitify' ); ?>
			</button>
		</header>

		<?php require PLUGITIFY_PATH . 'src/Views/Admin/partials/mu-plugin-notice.php'; ?>

		<?php if ( ! empty( $saved ) ) : ?>
			<div class="pty-notice pty-notice--success" role="status">
				<?php esc_html_e( 'تنظیمات با موفقیت ذخیره شد.', 'plugitify' ); ?>
			</div>
		<?php endif; ?>

		<section class="pty-panel pty-settings-panel">
			<form
				class="pty-form pty-settings-form"
				method="post"
				action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
				id="plugitify-settings-form"
			>
				<input type="hidden" name="action" value="plugitify_save_settings">
				<?php wp_nonce_field( 'plugitify_save_settings', 'plugitify_settings_nonce' ); ?>

				<div class="pty-settings-card">
					<aside class="pty-settings-card__rail" aria-hidden="true">
						<span class="pty-settings-card__rail-label"><?php esc_html_e( 'هوش مصنوعی', 'plugitify' ); ?></span>
					</aside>

					<div class="pty-settings-card__body">
						<div class="pty-settings-grid">
							<label class="pty-field">
								<span class="pty-field__label"><?php esc_html_e( 'سرویس‌دهنده', 'plugitify' ); ?></span>
								<select name="provider" id="plugitify-ai-provider" data-pty-provider required>
									<optgroup label="<?php esc_attr_e( 'بین‌المللی', 'plugitify' ); ?>">
										<?php foreach ( $providers as $providerId => $provider ) : ?>
											<?php if ( $provider['group'] !== 'global' ) : ?>
												<?php continue; ?>
											<?php endif; ?>
											<option
												value="<?php echo esc_attr( $providerId ); ?>"
												<?php selected( $settings['provider'], $providerId ); ?>
											>
												<?php echo esc_html( $provider['label'] ); ?>
											</option>
										<?php endforeach; ?>
									</optgroup>
									<optgroup label="<?php esc_attr_e( 'ایرانی', 'plugitify' ); ?>">
										<?php foreach ( $providers as $providerId => $provider ) : ?>
											<?php if ( $provider['group'] !== 'iran' ) : ?>
												<?php continue; ?>
											<?php endif; ?>
											<option
												value="<?php echo esc_attr( $providerId ); ?>"
												<?php selected( $settings['provider'], $providerId ); ?>
											>
												<?php echo esc_html( $provider['label'] ); ?>
											</option>
										<?php endforeach; ?>
									</optgroup>
								</select>
								<span class="pty-field__hint">
									<?php esc_html_e( 'OpenRouter دسترسی یکپارچه به مدل‌های بین‌المللی متنوع می‌دهد؛ GapGPT و AvalAI نیز در صورت دسترسی از داخل ایران قابل استفاده‌اند.', 'plugitify' ); ?>
								</span>
							</label>

							<label class="pty-field">
								<span class="pty-field__label"><?php esc_html_e( 'مدل', 'plugitify' ); ?></span>
								<select name="model" id="plugitify-ai-model" data-pty-model required>
									<?php foreach ( $currentModels as $modelId => $modelLabel ) : ?>
										<option
											value="<?php echo esc_attr( $modelId ); ?>"
											<?php selected( $settings['model'], $modelId ); ?>
										>
											<?php echo esc_html( $modelLabel ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<span class="pty-field__hint">
									<?php esc_html_e( 'لیست مدل‌ها بر اساس سرویس‌دهنده انتخاب‌شده به‌روز می‌شود.', 'plugitify' ); ?>
								</span>
							</label>

							<label class="pty-field pty-field--full">
								<span class="pty-field__label"><?php esc_html_e( 'کلید API', 'plugitify' ); ?></span>
								<span class="pty-password-field">
									<input
										type="password"
										name="api_key"
										id="plugitify-ai-api-key"
										value="<?php echo esc_attr( $settings['api_key'] ); ?>"
										autocomplete="off"
										dir="ltr"
										placeholder="sk-..."
									>
									<button
										type="button"
										class="pty-password-toggle"
										data-pty-password-toggle
										aria-label="<?php esc_attr_e( 'نمایش کلید API', 'plugitify' ); ?>"
										title="<?php esc_attr_e( 'نمایش کلید API', 'plugitify' ); ?>"
									>
										<svg class="pty-password-toggle__show" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
											<path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"></path>
											<circle cx="12" cy="12" r="2.5"></circle>
										</svg>
										<svg class="pty-password-toggle__hide" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" hidden>
											<path d="m3 3 18 18"></path>
											<path d="M10.6 5.1A10.8 10.8 0 0 1 12 5c6 0 9.5 7 9.5 7a17.7 17.7 0 0 1-3.1 3.9"></path>
											<path d="M6.7 6.7C4.1 8.2 2.5 12 2.5 12s3.5 7 9.5 7c1.3 0 2.5-.3 3.5-.8"></path>
											<path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"></path>
										</svg>
									</button>
								</span>
								<span class="pty-field__hint">
									<?php esc_html_e( 'کلید فقط روی سرور شما در option وردپرس ذخیره می‌شود.', 'plugitify' ); ?>
								</span>
								<span class="pty-test-connection">
									<span class="pty-test-connection__row">
										<button type="button" class="pty-btn pty-btn--ghost pty-btn--sm" data-pty-test-connection>
											<?php esc_html_e( 'تست اتصال', 'plugitify' ); ?>
										</button>
										<span class="pty-test-connection__result" data-pty-test-connection-result aria-live="polite"></span>
									</span>
									<span class="pty-field__hint">
										<?php esc_html_e( 'درخواست‌ها به مدل هوش مصنوعی مستقیماً از مرورگر سیستم خودتان فرستاده می‌شود و اصلاً از هاست عبور نمی‌کند؛ یعنی برقراری ارتباط با مدل به اینترنت سیستم خودتان بستگی دارد، نه به اینترنت هاست.', 'plugitify' ); ?>
									</span>
								</span>
							</label>
						</div>
					</div>
				</div>

				<footer class="pty-settings-footer">
					<button type="submit" class="pty-btn pty-btn--primary">
						<?php esc_html_e( 'ذخیره تنظیمات', 'plugitify' ); ?>
					</button>
				</footer>
			</form>
		</section>

		<div class="pty-toast" data-pty-toast hidden role="status" aria-live="polite"></div>
	</div>
</div>
