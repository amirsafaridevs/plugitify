<?php
/**
 * @var array<string, array<string, string|bool>> $plugins
 * @var array{ready: bool, state: string, source: string, sourceReadable: bool, targetDir: string, target: string, filename: string} $muPluginStatus
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$pluginCount   = count( $plugins );
$activeCount   = 0;
$inactiveCount = 0;

foreach ( $plugins as $pluginData ) {
	if ( ! empty( $pluginData['IsActive'] ) ) {
		$activeCount++;
	} else {
		$inactiveCount++;
	}
}

// The studio is served by the mu-plugin loader, so creating is blocked until it exists.
$creationBlocked = 'missing' === $muPluginStatus['state'] || 'undefined-dir' === $muPluginStatus['state'];
$createDisabled  = $creationBlocked ? ' disabled aria-disabled="true"' : '';
$createTitle     = $creationBlocked
	? ' title="' . esc_attr__( 'تا زمانی که فایل mu-plugin کپی نشود، ساخت افزونه‌ی جدید ممکن نیست.', 'plugitify' ) . '"'
	: '';
?>
<div class="wrap">
	<div class="pty" id="pty-dashboard" dir="rtl">
		<header class="pty-hero">
			<div class="pty-hero__copy">
				<p class="pty-eyebrow"><?php esc_html_e( 'پلاگیتی', 'plugitify' ); ?></p>
				<h1><?php esc_html_e( 'افزونه‌ها', 'plugitify' ); ?></h1>
				<p class="pty-hero__lede">
					<?php esc_html_e( 'افزونه‌های ساخته‌شده با پلاگیتی را از همین‌جا مدیریت کنید — ویرایش در استودیو یا حذف کامل.', 'plugitify' ); ?>
				</p>
			</div>
			<button type="button" class="pty-btn pty-btn--primary" id="plugitify-open-create-modal" data-pty-create<?php echo $createDisabled . $createTitle; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built above with esc_attr__(). ?>>
				<svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M8 3v10M3 8h10" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
				<?php esc_html_e( 'افزودن افزونه جدید', 'plugitify' ); ?>
			</button>
		</header>

		<?php require PLUGITIFY_PATH . 'src/Views/Admin/partials/mu-plugin-notice.php'; ?>

		<section class="pty-panel">
			<div class="pty-toolbar">
				<nav class="pty-tabs" aria-label="<?php esc_attr_e( 'فیلتر افزونه‌ها', 'plugitify' ); ?>">
					<button type="button" class="pty-tabs__link is-active" data-pty-tab="all" aria-pressed="true">
						<?php esc_html_e( 'همه', 'plugitify' ); ?>
						(<span data-pty-tab-count="all"><?php echo esc_html( (string) $pluginCount ); ?></span>)
					</button>
					<button type="button" class="pty-tabs__link" data-pty-tab="active" aria-pressed="false">
						<?php esc_html_e( 'فعال', 'plugitify' ); ?>
						(<span data-pty-tab-count="active"><?php echo esc_html( (string) $activeCount ); ?></span>)
					</button>
					<button type="button" class="pty-tabs__link" data-pty-tab="inactive" aria-pressed="false">
						<?php esc_html_e( 'غیرفعال', 'plugitify' ); ?>
						(<span data-pty-tab-count="inactive"><?php echo esc_html( (string) $inactiveCount ); ?></span>)
					</button>
				</nav>

				<label class="pty-search">
					<span class="screen-reader-text"><?php esc_html_e( 'جستجوی افزونه', 'plugitify' ); ?></span>
					<svg class="pty-search__icon" width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
						<circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2"/>
						<path d="M20 20l-3.5-3.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
					</svg>
					<input
						type="search"
						id="plugitify-search"
						data-pty-search
						placeholder="<?php esc_attr_e( 'جستجو بر اساس نام، اسلاگ، توضیحات…', 'plugitify' ); ?>"
						autocomplete="off"
					>
				</label>
			</div>

			<div class="pty-list" data-pty-list>
				<?php if ( empty( $plugins ) ) : ?>
					<div class="pty-empty pty-empty--inline" data-pty-empty>
						<div class="pty-empty__icon" aria-hidden="true">
							<svg width="40" height="40" viewBox="0 0 40 40" fill="none"><rect x="6" y="8" width="28" height="24" rx="4" stroke="currentColor" stroke-width="1.6"/><path d="M6 16h28M14 8v8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
						</div>
						<h3><?php esc_html_e( 'هنوز افزونه‌ای نیست', 'plugitify' ); ?></h3>
						<p><?php esc_html_e( 'اولین افزونه را بسازید تا از همین‌جا مدیریتش کنید.', 'plugitify' ); ?></p>
						<button type="button" class="pty-btn pty-btn--primary" data-pty-create<?php echo $createDisabled . $createTitle; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built above with esc_attr__(). ?>>
							<?php esc_html_e( 'افزودن افزونه جدید', 'plugitify' ); ?>
						</button>
					</div>
				<?php else : ?>
					<?php foreach ( $plugins as $pluginFile => $pluginData ) : ?>
						<?php
						$name        = (string) $pluginData['Name'];
						$slug        = (string) $pluginData['Slug'];
						$version     = (string) $pluginData['Version'];
						$description = (string) $pluginData['Description'];
						$isActive    = ! empty( $pluginData['IsActive'] );
						$searchBlob  = function_exists( 'mb_strtolower' )
							? mb_strtolower( $name . ' ' . $slug . ' ' . $description )
							: strtolower( $name . ' ' . $slug . ' ' . $description );
						?>
						<article
							class="pty-card"
							data-plugitify-row
							data-pty-row
							data-status="<?php echo $isActive ? 'active' : 'inactive'; ?>"
							data-search="<?php echo esc_attr( $searchBlob ); ?>"
						>
							<div class="pty-card__body">
								<div class="pty-card__col pty-card__col--title">
									<h3 class="pty-card__title"><?php echo esc_html( $name ); ?></h3>
								</div>

								<div class="pty-card__col pty-card__col--meta">
									<?php if ( '' !== $version ) : ?>
										<span class="pty-card__meta-item" title="<?php esc_attr_e( 'نسخه', 'plugitify' ); ?>">
											<svg width="13" height="13" viewBox="0 0 24 24" fill="none" aria-hidden="true">
												<path d="M12 3 4.5 7.5v9L12 21l7.5-4.5v-9L12 3Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
												<path d="M12 12 4.5 7.5M12 12l7.5-4.5M12 12v9" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
											</svg>
											<?php
											/* translators: %s: plugin version number. */
											echo esc_html( sprintf( __( 'نسخه %s', 'plugitify' ), $version ) );
											?>
										</span>
									<?php endif; ?>
								</div>

								<div class="pty-card__col pty-card__col--extra">
									<?php if ( '' !== $description ) : ?>
										<span class="pty-card__meta-item pty-card__meta-item--desc" title="<?php echo esc_attr( $description ); ?>">
											<svg width="13" height="13" viewBox="0 0 24 24" fill="none" aria-hidden="true">
												<path d="M7 4h7l5 5v11a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
												<path d="M14 4v5h5M9 13h6M9 17h4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
											</svg>
											<?php echo esc_html( $description ); ?>
										</span>
									<?php endif; ?>
								</div>
							</div>

							<div class="pty-card__actions">
								<button
									type="button"
									class="pty-switch<?php echo $isActive ? ' is-active' : ''; ?>"
									role="switch"
									aria-checked="<?php echo $isActive ? 'true' : 'false'; ?>"
									data-plugitify-toggle
									data-slug="<?php echo esc_attr( $slug ); ?>"
									title="<?php echo $isActive ? esc_attr__( 'غیرفعال‌سازی', 'plugitify' ) : esc_attr__( 'فعال‌سازی', 'plugitify' ); ?>"
								>
									<span class="pty-switch__thumb"></span>
									<span class="screen-reader-text">
										<?php echo $isActive ? esc_html__( 'فعال', 'plugitify' ) : esc_html__( 'غیرفعال', 'plugitify' ); ?>
									</span>
								</button>
								<a
									class="pty-card__edit"
									href="<?php echo esc_url( home_url( '/plugitify/v1/chat/' . rawurlencode( $slug ) ) ); ?>"
									title="<?php esc_attr_e( 'ویرایش', 'plugitify' ); ?>"
								>
									<svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
										<path d="M4 20h4l11.2-11.2a2.1 2.1 0 0 0-3-3L5 17v3Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
										<path d="M13.5 6.5l3 3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
									</svg>
									<span class="screen-reader-text"><?php esc_html_e( 'ویرایش', 'plugitify' ); ?></span>
								</a>
								<button
									type="button"
									class="pty-card__delete"
									data-plugitify-delete
									data-slug="<?php echo esc_attr( $slug ); ?>"
									data-name="<?php echo esc_attr( $name ); ?>"
									title="<?php esc_attr_e( 'حذف', 'plugitify' ); ?>"
								>
									<svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
										<path d="M5 7h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
										<path d="M10 11v6M14 11v6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
										<path d="M7 7l1 12a2 2 0 0 0 2 2h4a2 2 0 0 0 2-2l1-12" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
										<path d="M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
									</svg>
									<span class="screen-reader-text"><?php esc_html_e( 'حذف', 'plugitify' ); ?></span>
								</button>
							</div>
						</article>
					<?php endforeach; ?>

					<div class="pty-empty pty-empty--inline" id="plugitify-no-results" data-pty-no-results hidden>
						<div class="pty-empty__icon" aria-hidden="true">
							<svg width="40" height="40" viewBox="0 0 40 40" fill="none"><circle cx="18" cy="18" r="10" stroke="currentColor" stroke-width="1.6"/><path d="M26 26l6 6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
						</div>
						<h3><?php esc_html_e( 'نتیجه‌ای یافت نشد', 'plugitify' ); ?></h3>
						<p><?php esc_html_e( 'عبارت جستجو یا فیلتر را تغییر دهید.', 'plugitify' ); ?></p>
					</div>
				<?php endif; ?>
			</div>
		</section>

		<div class="pty-modal" data-pty-delete-modal hidden>
			<div class="pty-modal__backdrop" data-pty-delete-close></div>
			<div class="pty-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="pty-delete-title">
				<h3 id="pty-delete-title" data-pty-delete-title></h3>
				<p data-pty-delete-body></p>
				<p class="pty-modal__target" data-pty-delete-target></p>
				<div class="pty-modal__actions">
					<button type="button" class="pty-btn pty-btn--ghost" data-pty-delete-close></button>
					<button type="button" class="pty-btn pty-btn--danger" data-pty-delete-confirm></button>
				</div>
			</div>
		</div>

		<div class="pty-modal" id="plugitify-create-modal" data-pty-create-modal hidden>
			<div class="pty-modal__backdrop" data-plugitify-close></div>
			<div
				class="pty-modal__dialog"
				role="dialog"
				aria-modal="true"
				aria-labelledby="plugitify-modal-title"
			>
				<h3 id="plugitify-modal-title"><?php esc_html_e( 'افزودن افزونه جدید', 'plugitify' ); ?></h3>
				<p><?php esc_html_e( 'نام، اسلاگ و توضیح کوتاه افزونه را وارد کنید.', 'plugitify' ); ?></p>
				<form class="pty-form" id="plugitify-create-form">
					<label class="pty-field">
						<span class="pty-field__label"><?php esc_html_e( 'نام افزونه', 'plugitify' ); ?></span>
						<input type="text" id="plugitify-plugin-name" name="name" required autocomplete="off" placeholder="<?php esc_attr_e( 'مثلاً فروشگاه من', 'plugitify' ); ?>">
					</label>
					<label class="pty-field">
						<span class="pty-field__label"><?php esc_html_e( 'اسلاگ افزونه (انگلیسی)', 'plugitify' ); ?></span>
						<input
							type="text"
							id="plugitify-plugin-slug"
							name="slug"
							pattern="[a-z0-9]+(-[a-z0-9]+)*"
							dir="ltr"
							required
							autocomplete="off"
							placeholder="my-shop"
						>
						<span class="pty-field__hint">
							<?php esc_html_e( 'فقط حروف کوچک انگلیسی، عدد و خط تیره؛ همان نام پوشه‌ی افزونه خواهد بود.', 'plugitify' ); ?>
						</span>
					</label>
					<label class="pty-field">
						<span class="pty-field__label"><?php esc_html_e( 'توضیحات', 'plugitify' ); ?></span>
						<input type="text" id="plugitify-plugin-description" name="description" required autocomplete="off" placeholder="<?php esc_attr_e( 'توضیح کوتاه', 'plugitify' ); ?>">
					</label>
					<p class="pty-form__error" id="plugitify-create-error" hidden></p>
					<div class="pty-modal__actions">
						<button type="button" class="pty-btn pty-btn--ghost" data-plugitify-close>
							<?php esc_html_e( 'انصراف', 'plugitify' ); ?>
						</button>
						<button type="submit" class="pty-btn pty-btn--primary">
							<?php esc_html_e( 'ایجاد', 'plugitify' ); ?>
						</button>
					</div>
				</form>
			</div>
		</div>

		<div class="pty-toast" data-pty-toast hidden role="status" aria-live="polite"></div>
	</div>
</div>
