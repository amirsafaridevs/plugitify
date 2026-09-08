<?php
/**
 * @var array<string, array<string, string>> $plugins
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap plugitify-plugins">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'پلاگیتی', 'plugitify' ); ?></h1>
	<button type="button" id="plugitify-open-create-modal" class="page-title-action">
		<?php esc_html_e( 'افزودن افزونه جدید', 'plugitify' ); ?>
	</button>
	<hr class="wp-header-end">

	<p class="plugitify-plugins__intro">
		<?php esc_html_e( 'افزونه‌های ساخته‌شده با پلاگیتی فای را از همین‌جا مدیریت کنید.', 'plugitify' ); ?>
	</p>

	<?php if ( empty( $plugins ) ) : ?>
		<div class="plugitify-empty">
			<span class="dashicons dashicons-admin-plugins"></span>
			<p><?php esc_html_e( 'هنوز افزونه‌ای با پلاگیتی ساخته نشده است.', 'plugitify' ); ?></p>
		</div>
	<?php else : ?>
		<div class="plugitify-toolbar">
			<div class="plugitify-toolbar__search">
				<span class="dashicons dashicons-search" aria-hidden="true"></span>
				<input
					type="search"
					id="plugitify-search"
					placeholder="<?php esc_attr_e( 'جستجوی افزونه…', 'plugitify' ); ?>"
				>
			</div>
			<div class="plugitify-toolbar__bulk">
				<label class="plugitify-toolbar__select-all">
					<input type="checkbox" id="plugitify-select-all">
					<?php esc_html_e( 'انتخاب همه', 'plugitify' ); ?>
				</label>
				<button type="button" class="button" data-plugitify-bulk="activate" disabled>
					<?php esc_html_e( 'فعال‌سازی', 'plugitify' ); ?>
				</button>
				<button type="button" class="button" data-plugitify-bulk="deactivate" disabled>
					<?php esc_html_e( 'غیرفعال‌سازی', 'plugitify' ); ?>
				</button>
				<button type="button" class="button plugitify-button--danger" data-plugitify-bulk="delete" disabled>
					<?php esc_html_e( 'حذف', 'plugitify' ); ?>
				</button>
			</div>
		</div>

		<ul class="plugitify-plugins__list">
			<?php foreach ( $plugins as $pluginFile => $pluginData ) : ?>
				<li
					class="plugitify-plugins__item"
					data-plugitify-row
					data-search="<?php echo esc_attr( function_exists( 'mb_strtolower' ) ? mb_strtolower( $pluginData['Name'] . ' ' . $pluginData['Description'] ) : strtolower( $pluginData['Name'] . ' ' . $pluginData['Description'] ) ); ?>"
				>
					<label class="plugitify-plugins__select">
						<input
							type="checkbox"
							data-plugitify-row-checkbox
							data-slug="<?php echo esc_attr( $pluginData['Slug'] ); ?>"
						>
						<span class="screen-reader-text"><?php esc_html_e( 'انتخاب', 'plugitify' ); ?></span>
					</label>
					<div class="plugitify-plugins__info">
						<span class="plugitify-plugins__name"><?php echo esc_html( $pluginData['Name'] ); ?></span>
						<?php if ( '' !== $pluginData['Version'] ) : ?>
							<span class="plugitify-plugins__version">
								<?php
								/* translators: %s: plugin version number. */
								echo esc_html( sprintf( __( 'نسخه %s', 'plugitify' ), $pluginData['Version'] ) );
								?>
							</span>
						<?php endif; ?>
						<?php if ( '' !== $pluginData['Description'] ) : ?>
							<p class="plugitify-plugins__description"><?php echo esc_html( $pluginData['Description'] ); ?></p>
						<?php endif; ?>
					</div>
					<div class="plugitify-plugins__actions">
						<button
							type="button"
							class="plugitify-switch<?php echo $pluginData['IsActive'] ? ' is-active' : ''; ?>"
							role="switch"
							aria-checked="<?php echo $pluginData['IsActive'] ? 'true' : 'false'; ?>"
							data-plugitify-toggle
							data-slug="<?php echo esc_attr( $pluginData['Slug'] ); ?>"
							title="<?php echo $pluginData['IsActive'] ? esc_attr__( 'غیرفعال‌سازی', 'plugitify' ) : esc_attr__( 'فعال‌سازی', 'plugitify' ); ?>"
						>
							<span class="plugitify-switch__thumb"></span>
							<span class="screen-reader-text">
								<?php echo $pluginData['IsActive'] ? esc_html__( 'فعال', 'plugitify' ) : esc_html__( 'غیرفعال', 'plugitify' ); ?>
							</span>
						</button>
						<a
							href="<?php echo esc_url( home_url( '/plugitify/v1/chat/' . rawurlencode( $pluginData['Slug'] ) ) ); ?>"
							class="plugitify-icon-button"
							title="<?php esc_attr_e( 'ویرایش', 'plugitify' ); ?>"
						>
							<span class="dashicons dashicons-edit"></span>
							<span class="screen-reader-text"><?php esc_html_e( 'ویرایش', 'plugitify' ); ?></span>
						</a>
						<button
							type="button"
							class="plugitify-icon-button plugitify-icon-button--danger"
							data-plugitify-delete
							data-slug="<?php echo esc_attr( $pluginData['Slug'] ); ?>"
							data-name="<?php echo esc_attr( $pluginData['Name'] ); ?>"
							title="<?php esc_attr_e( 'حذف', 'plugitify' ); ?>"
						>
							<span class="dashicons dashicons-trash"></span>
							<span class="screen-reader-text"><?php esc_html_e( 'حذف', 'plugitify' ); ?></span>
						</button>
					</div>
				</li>
			<?php endforeach; ?>
		</ul>
		<p id="plugitify-no-results" class="plugitify-plugins__no-results" hidden>
			<?php esc_html_e( 'نتیجه‌ای یافت نشد.', 'plugitify' ); ?>
		</p>
	<?php endif; ?>

	<div id="plugitify-create-modal" class="plugitify-modal" hidden>
		<div class="plugitify-modal__overlay" data-plugitify-close></div>
		<div class="plugitify-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="plugitify-modal-title">
			<div class="plugitify-modal__header">
				<h2 id="plugitify-modal-title"><?php esc_html_e( 'افزودن افزونه جدید', 'plugitify' ); ?></h2>
				<button
					type="button"
					class="plugitify-modal__close"
					data-plugitify-close
					aria-label="<?php esc_attr_e( 'بستن', 'plugitify' ); ?>"
				>&times;</button>
			</div>
			<form id="plugitify-create-form" class="plugitify-modal__body">
				<p class="plugitify-field">
					<label for="plugitify-plugin-name"><?php esc_html_e( 'نام افزونه', 'plugitify' ); ?></label>
					<input type="text" id="plugitify-plugin-name" name="name" required>
				</p>
				<p class="plugitify-field">
					<label for="plugitify-plugin-slug"><?php esc_html_e( 'اسلاگ افزونه (انگلیسی)', 'plugitify' ); ?></label>
					<input
						type="text"
						id="plugitify-plugin-slug"
						name="slug"
						pattern="[a-z0-9]+(-[a-z0-9]+)*"
						dir="ltr"
						required
					>
					<span class="description">
						<?php esc_html_e( 'فقط حروف کوچک انگلیسی، عدد و خط تیره؛ همان نام پوشه‌ی افزونه خواهد بود.', 'plugitify' ); ?>
					</span>
				</p>
				<p class="plugitify-field">
					<label for="plugitify-plugin-description"><?php esc_html_e( 'توضیحات', 'plugitify' ); ?></label>
					<input type="text" id="plugitify-plugin-description" name="description" required>
				</p>
				<p class="plugitify-modal__error" id="plugitify-create-error" hidden></p>
				<p class="plugitify-modal__actions">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'ایجاد', 'plugitify' ); ?></button>
					<button type="button" class="button" data-plugitify-close><?php esc_html_e( 'انصراف', 'plugitify' ); ?></button>
				</p>
			</form>
		</div>
	</div>
</div>
