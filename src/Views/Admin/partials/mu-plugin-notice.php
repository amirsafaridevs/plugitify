<?php
/**
 * Warning banner shown when wp-content/mu-plugins/plugitify.php is missing or stale.
 *
 * @var array{ready: bool, state: string, source: string, sourceReadable: bool, targetDir: string, target: string, filename: string} $muPluginStatus
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $muPluginStatus ) || ! empty( $muPluginStatus['ready'] ) ) {
	return;
}

$isOutdated = 'outdated' === $muPluginStatus['state'];
$targetDir  = (string) $muPluginStatus['targetDir'];
$hasTarget  = '' !== $targetDir;
?>
<div class="pty-notice pty-notice--warning" role="alert" data-pty-mu-notice>
	<div class="pty-notice__icon" aria-hidden="true">
		<svg width="20" height="20" viewBox="0 0 24 24" fill="none">
			<path d="M12 4.5 2.8 20h18.4L12 4.5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
			<path d="M12 10v4.2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
			<circle cx="12" cy="17" r="1.1" fill="currentColor"/>
		</svg>
	</div>

	<div class="pty-notice__body">
		<h2 class="pty-notice__title">
			<?php if ( $isOutdated ) : ?>
				<?php esc_html_e( 'فایل بارگذار پلاگیتی به‌روز نیست', 'plugitify' ); ?>
			<?php else : ?>
				<?php esc_html_e( 'فایل mu-plugin پلاگیتی نصب نشد', 'plugitify' ); ?>
			<?php endif; ?>
		</h2>

		<p class="pty-notice__text">
			<?php if ( $isOutdated ) : ?>
				<?php esc_html_e( 'نسخه‌ی موجود در پوشه‌ی mu-plugins با نسخه‌ی این افزونه یکی نیست و به دلیل محدودیت‌های دسترسی نوشتن، به‌روزرسانی خودکار انجام نشد. لطفاً فایل را به‌صورت دستی جایگزین کنید.', 'plugitify' ); ?>
			<?php else : ?>
				<?php esc_html_e( 'فایل بارگذار پلاگیتی به دلیل محدودیت‌های دسترسی نوشتن، در پوشه‌ی mu-plugins وردپرس قرار نگرفت. تا زمانی که این فایل کپی نشود، استودیوی پلاگیتی کار نمی‌کند و امکان ساخت افزونه‌ی جدید غیرفعال است. لطفاً فایل را به‌صورت دستی کپی کنید.', 'plugitify' ); ?>
			<?php endif; ?>
		</p>

		<ol class="pty-notice__steps">
			<li>
				<span class="pty-notice__step-label"><?php esc_html_e( '۱. این فایل را کپی کنید:', 'plugitify' ); ?></span>
				<code class="pty-notice__path" dir="ltr"><?php echo esc_html( $muPluginStatus['source'] ); ?></code>
				<button
					type="button"
					class="pty-notice__copy"
					data-pty-copy="<?php echo esc_attr( $muPluginStatus['source'] ); ?>"
				>
					<?php esc_html_e( 'کپی مسیر', 'plugitify' ); ?>
				</button>
			</li>
			<li>
				<span class="pty-notice__step-label">
					<?php esc_html_e( '۲. آن را داخل این پوشه بگذارید (اگر پوشه وجود ندارد، بسازید):', 'plugitify' ); ?>
				</span>
				<?php if ( $hasTarget ) : ?>
					<code class="pty-notice__path" dir="ltr"><?php echo esc_html( $targetDir ); ?></code>
					<button
						type="button"
						class="pty-notice__copy"
						data-pty-copy="<?php echo esc_attr( $targetDir ); ?>"
					>
						<?php esc_html_e( 'کپی مسیر', 'plugitify' ); ?>
					</button>
				<?php else : ?>
					<code class="pty-notice__path" dir="ltr">wp-content/mu-plugins</code>
				<?php endif; ?>
			</li>
			<li>
				<span class="pty-notice__step-label">
					<?php
					printf(
						/* translators: %s: mu-plugin file name. */
						esc_html__( '۳. نام فایل را دقیقاً %s نگه دارید؛ نتیجه‌ی نهایی باید این مسیر باشد:', 'plugitify' ),
						'<code dir="ltr">' . esc_html( $muPluginStatus['filename'] ) . '</code>'
					);
					?>
				</span>
				<?php if ( $hasTarget ) : ?>
					<code class="pty-notice__path" dir="ltr"><?php echo esc_html( $muPluginStatus['target'] ); ?></code>
					<button
						type="button"
						class="pty-notice__copy"
						data-pty-copy="<?php echo esc_attr( $muPluginStatus['target'] ); ?>"
					>
						<?php esc_html_e( 'کپی مسیر', 'plugitify' ); ?>
					</button>
				<?php endif; ?>
			</li>
			<li>
				<span class="pty-notice__step-label">
					<?php esc_html_e( '۴. سپس همین صفحه را تازه‌سازی کنید؛ این پیام باید از بین برود.', 'plugitify' ); ?>
				</span>
				<a class="pty-notice__copy pty-notice__copy--link" href="<?php echo esc_url( remove_query_arg( 'plugitify-recheck' ) ); ?>">
					<?php esc_html_e( 'بررسی دوباره', 'plugitify' ); ?>
				</a>
			</li>
		</ol>

		<?php if ( ! $muPluginStatus['sourceReadable'] ) : ?>
			<p class="pty-notice__text pty-notice__text--muted">
				<?php esc_html_e( 'توجه: فایل مبدأ در پوشه‌ی پلاگیتی خوانده نشد. لطفاً افزونه را دوباره نصب کنید.', 'plugitify' ); ?>
			</p>
		<?php endif; ?>

		<p class="pty-notice__text pty-notice__text--muted">
			<?php esc_html_e( 'اگر به فایل‌های سرور دسترسی ندارید، این مسیرها را برای مدیر هاست بفرستید تا فایل را جای‌گذاری کند.', 'plugitify' ); ?>
		</p>
	</div>
</div>
