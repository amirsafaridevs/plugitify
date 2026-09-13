<?php

/**
 * Build-time injector for the RTL-Theme license gate.
 *
 * Run by build.sh against the COPY of the plugin inside ./final — never against
 * the working tree. It patches src/App/App.php so the plugin stays completely
 * inert (no menus, no providers, no hooks) until the product license is active.
 *
 * Usage: php build/inject-rtl-license.php <path/to/built/src/App/App.php>
 */

declare(strict_types=1);

const LICENSE_CLASS = 'RTL_License_59a8551a423f54bf';
const LICENSE_SHA1  = '993cdb73bc3d92c8fb7148e09d9ce2534a0d863b';

/**
 * Abort the build with a readable message.
 */
function fail(string $message): void
{
	fwrite(STDERR, "!! inject-rtl-license: {$message}\n");
	exit(1);
}

// ---------------------------------------------------------------------------
// 1. Locate the built App.php and the license file that must sit in the root
// ---------------------------------------------------------------------------

$appFile = $argv[1] ?? '';

if ($appFile === '' || ! is_file($appFile)) {
	fail('built App.php not found — pass its path as the first argument.');
}

// App.php lives at src/App/App.php, so the plugin root is three levels up.
// The encrypted class ships untouched there.
$licenseFile = dirname($appFile, 3) . DIRECTORY_SEPARATOR . LICENSE_CLASS . '.php';

if (! is_file($licenseFile)) {
	fail(sprintf('license file is missing from the built plugin root (%s).', $licenseFile));
}

$actualHash = sha1_file($licenseFile);

if ($actualHash !== LICENSE_SHA1) {
	fail(sprintf(
		"license file hash mismatch.\n" .
		"   expected : %s\n" .
		"   actual   : %s\n" .
		"   The file was re-encrypted by rtl-theme.com. Update LICENSE_SHA1 in this\n" .
		"   script AND the hash inside the injected snippet below to the new value,\n" .
		"   otherwise the released plugin can never be activated.",
		LICENSE_SHA1,
		(string) $actualHash
	));
}

// ---------------------------------------------------------------------------
// 2. The code that gets injected
// ---------------------------------------------------------------------------

$guard = <<<'PHP'
		// Nothing below this line runs until the product license is activated.
		if ( $this->rtlLicenseIsActive() !== true ) {
			add_action( 'admin_notices', [ $this, 'rtlLicenseInactiveNotice' ] );

			return;
		}


PHP;

$methods = <<<'PHP'
	/**
	 * Product license check — injected at build time, absent from the dev tree.
	 */
	private function rtlLicenseIsActive(): bool
	{
		$rtlLicenseIsProductActive = false;

		// --------------------------------------------------------------------------------------------------- Start RTL License
		// dirname( __DIR__, 2 ): this file is src/App/App.php, the license class sits in the plugin root.
		$rtlLicenseClassName  = 'RTL_License_59a8551a423f54bf';
		$rtlLicenseFilePath   = dirname( __DIR__, 2 ) . DIRECTORY_SEPARATOR . $rtlLicenseClassName . '.php';
		$rtlLicenseFileHash   = @sha1_file($rtlLicenseFilePath);

		if ( $rtlLicenseFileHash === '993cdb73bc3d92c8fb7148e09d9ce2534a0d863b' && file_exists($rtlLicenseFilePath) ) {
			require_once $rtlLicenseFilePath;

			if ( class_exists($rtlLicenseClassName) && method_exists($rtlLicenseClassName, 'isActive') ) {
				$rtlLicenseClass = new $rtlLicenseClassName();

				if ( $rtlLicenseClass->{'isActive'}() === true ) {
					// Product is Active Now, Enable Pro Features
					$rtlLicenseIsProductActive = true;
				}
			}
		}
		// ----------------------------------------------------------------------------------------------------- End RTL License

		return $rtlLicenseIsProductActive;
	}

	/**
	 * Admin notice shown while the license is not active — injected at build time.
	 */
	public function rtlLicenseInactiveNotice(): void
	{
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__(
				'افزونه «پلاگیتی‌فای» تا فعال‌سازی لایسنس کار نمی‌کند. لطفاً کد لایسنس خود را در صفحهٔ فعال‌سازی محصول وارد کنید.',
				'plugitify'
			)
		);
	}
PHP;

// The snippet is copied verbatim from rtl-theme.com, so keep it in sync with the
// constants this script verifies against.
if (strpos($methods, LICENSE_SHA1) === false || strpos($methods, LICENSE_CLASS) === false) {
	fail('injected snippet no longer matches LICENSE_CLASS / LICENSE_SHA1.');
}

// ---------------------------------------------------------------------------
// 3. Patch the built App.php
// ---------------------------------------------------------------------------

$source = file_get_contents($appFile);

if ($source === false) {
	fail(sprintf('cannot read %s.', $appFile));
}

if (strpos($source, 'Start RTL License') !== false) {
	fail('App.php already contains the license gate — build on a clean copy.');
}

// boot() is the single funnel: it loads the textdomain and registers every
// service provider, so returning early there leaves the plugin completely inert.
$anchor = "\tprivate function boot(): void\n\t{\n";

if (substr_count($source, $anchor) !== 1) {
	fail(
		'boot() anchor not found in App.php (expected exactly one tab-indented ' .
		'"private function boot(): void" followed by its opening brace). ' .
		'Update this script after refactoring App.'
	);
}

$source = str_replace($anchor, $anchor . $guard, $source);

// Insert the methods just before the class closing brace (column 0).
$classEnd = strrpos($source, "\n}");

if ($classEnd === false) {
	fail('class closing brace not found in App.php.');
}

$source = substr($source, 0, $classEnd) . "\n\n" . $methods . substr($source, $classEnd);

if (file_put_contents($appFile, $source) === false) {
	fail(sprintf('cannot write %s.', $appFile));
}

echo "    Gate   : license check injected into src/App/App.php\n";
