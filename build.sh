#!/usr/bin/env bash
# Build a clean distributable copy of the Plugitify plugin into ./final
#
#   ./build.sh                  build ./final/plugitify
#   ./build.sh --zip            also write ./final/plugitify-<version>.zip
#   ./build.sh --skip-npm       reuse agent.bundle.js as it is on disk
#   ./build.sh --skip-composer  reuse vendor/ as it is on disk
#   ./build.sh --no-minify      leave the hand-written CSS/JS readable
set -euo pipefail

ROOT="$(cd "$(dirname "$0")" && pwd)"
PLUGIN_SLUG="plugitify"
OUT_DIR="$ROOT/final/$PLUGIN_SLUG"
LICENSE_FILE="RTL_License_59a8551a423f54bf.php"

MAKE_ZIP=0
SKIP_NPM=0
SKIP_COMPOSER=0
SKIP_MINIFY=0

while [ $# -gt 0 ]; do
	case "$1" in
		--zip)           MAKE_ZIP=1 ;;
		--skip-npm)      SKIP_NPM=1 ;;
		--skip-composer) SKIP_COMPOSER=1 ;;
		--no-minify)     SKIP_MINIFY=1 ;;
		-h|--help)       sed -n '2,9p' "$0" | sed 's/^# \?//'; exit 0 ;;
		*)               echo "!! unknown option: $1" >&2; exit 1 ;;
	esac
	shift
done

echo "==> Building $PLUGIN_SLUG..."

# The license gate is a release-only concern: it must exist in ./final and never
# in the working tree, so development and local testing stay unaffected.
if [ ! -f "$ROOT/$LICENSE_FILE" ]; then
	echo "!! $LICENSE_FILE not found in the plugin root." >&2
	echo "   Download it from rtl-theme.com (license file step) and place it here." >&2
	exit 1
fi

if ! command -v php >/dev/null 2>&1; then
	echo "!! php CLI not found in PATH — needed to inject the license gate." >&2
	exit 1
fi

VERSION="$(sed -n "s/^[[:space:]]*define([[:space:]]*'PLUGITIFY_VERSION',[[:space:]]*'\([^']*\)'.*/\1/p" \
	"$ROOT/plugitify.php" | head -1)"

if [ -z "$VERSION" ]; then
	echo "!! could not read PLUGITIFY_VERSION from plugitify.php" >&2
	exit 1
fi

# --- Frontend: the shipped bundle is built from agent/src, which never ships ---
if [ "$SKIP_NPM" -eq 1 ]; then
	echo "    Bundle : skipped (--skip-npm), reusing the file on disk"
else
	if ! command -v npm >/dev/null 2>&1; then
		echo "!! npm not found in PATH — install Node.js or pass --skip-npm." >&2
		exit 1
	fi

	[ -d "$ROOT/agent/node_modules" ] || ( cd "$ROOT/agent" && npm ci --silent )

	# build.mjs minifies unless --dev/--watch is passed and writes straight to
	# src/muPlugin/view/assets/js/agent.bundle.js.
	( cd "$ROOT/agent" && npm run --silent build )
	echo "    Bundle : agent.bundle.js rebuilt"
fi

# --- PHP dependencies -------------------------------------------------------
# Deliberately not --classmap-authoritative: composer skips every
# Plugitify\muPlugin\* class from the classmap because the paths do not match the
# PSR-4 rule (core/agentTools.php, not Core/AgentTools.php). They are all
# require_once'd by hand today, so an authoritative classmap would work — but the
# moment someone adds a muPlugin class without that require, it would break in
# the release and nowhere else.
if [ "$SKIP_COMPOSER" -eq 1 ]; then
	echo "    Vendor : skipped (--skip-composer), reusing vendor/ on disk"
else
	if command -v composer >/dev/null 2>&1; then
		( cd "$ROOT" && composer install --no-dev --optimize-autoloader --no-interaction --quiet )
		echo "    Vendor : composer install --no-dev"
	else
		echo "    Vendor : composer not found, reusing vendor/ on disk"
	fi
fi

# Fresh output each run
rm -rf "$ROOT/final"
mkdir -p "$OUT_DIR"

# --- Essential plugin files only (whitelist) ---
cp "$ROOT/plugitify.php" "$OUT_DIR/"

cp -R "$ROOT/assets" "$OUT_DIR/"
cp -R "$ROOT/src" "$OUT_DIR/"
cp -R "$ROOT/vendor" "$OUT_DIR/"
cp -R "$ROOT/languages" "$OUT_DIR/"

# --- Drop dev scaffolding and junk that may live inside the copied trees ---
find "$OUT_DIR" -type d -name node_modules -prune -exec rm -rf {} + 2>/dev/null || true
find "$OUT_DIR" -type d \( -name doc -o -name docs -o -name test -o -name tests \
	-o -name Tests -o -name examples -o -name .github \) -prune -exec rm -rf {} + 2>/dev/null || true
rm -rf "$OUT_DIR/vendor/masterminds/html5/bin" 2>/dev/null || true
find "$OUT_DIR" -name '.git*' -exec rm -rf {} + 2>/dev/null || true
find "$OUT_DIR" -type f \( \
	-name '*.map' -o -name '*.ts' -o -name '*.tsx' -o -name 'tsconfig*.json' \
	-o -name 'package.json' -o -name 'package-lock.json' -o -name '*.mjs' \
	-o -name '.DS_Store' -o -name 'Thumbs.db' -o -name '.editorconfig' \
	-o -name 'phpunit*' -o -name '*.dist' -o -name 'Makefile' \
\) -delete 2>/dev/null || true
find "$OUT_DIR/vendor" -type f \( -iname '*.md' -o -iname '*.yml' -o -iname '*.yaml' \) \
	-delete 2>/dev/null || true

# --- Translations: only the compiled .mo is read at runtime ---
if command -v msgfmt >/dev/null 2>&1; then
	for po in "$OUT_DIR"/languages/*.po; do
		[ -f "$po" ] || continue
		msgfmt -o "${po%.po}.mo" "$po"
	done
	rm -f "$OUT_DIR"/languages/*.po
	echo "    Lang   : .po compiled to .mo"
fi

# --- Minify the hand-written assets. agent.bundle.js is already minified by
#     agent/build.mjs, so it is left alone. ---
ESBUILD="$ROOT/agent/node_modules/.bin/esbuild"

if [ "$SKIP_MINIFY" -eq 0 ] && [ -x "$ESBUILD" ]; then
	for rel in \
		"src/muPlugin/view/assets/js/chat.js" \
		"src/muPlugin/view/assets/css/chat.css" \
		"assets/admin/plugitify-admin.js" \
		"assets/admin/plugitify-admin.css"
	do
		[ -f "$OUT_DIR/$rel" ] || continue

		# No --bundle and no --format: this is a transform, so each file keeps its
		# module shape (chat.js is an IIFE loaded as type="module"). --charset=utf8
		# keeps the Persian UI strings as real characters instead of \u escapes.
		"$ESBUILD" "$OUT_DIR/$rel" --minify --charset=utf8 --legal-comments=none \
			--outfile="$OUT_DIR/$rel.min" >/dev/null 2>&1 \
			|| { echo "!! esbuild failed on $rel" >&2; exit 1; }

		mv -f "$OUT_DIR/$rel.min" "$OUT_DIR/$rel"
	done
	echo "    Assets : CSS/JS minified"
elif [ "$SKIP_MINIFY" -eq 1 ]; then
	echo "    Assets : left unminified (--no-minify)"
else
	echo "    Assets : esbuild not found, left unminified"
fi

# --- License gate (release only) ---
# The encrypted class is copied byte for byte into the plugin root; App.php
# resolves it with dirname(__DIR__, 2).
cp "$ROOT/$LICENSE_FILE" "$OUT_DIR/"
php "$ROOT/build/inject-rtl-license.php" "$OUT_DIR/src/App/App.php"
php -l "$OUT_DIR/src/App/App.php" >/dev/null

# --- Sanity checks on what is about to ship ---
# vendor/ is not linted: one php.exe per file is slow on Windows and the prune
# above never touches PHP there.
LINT_FAIL=0
while IFS= read -r f; do
	php -l "$f" >/dev/null 2>&1 || { echo "!! parse error: ${f#"$OUT_DIR"/}" >&2; LINT_FAIL=1; }
done < <(find "$OUT_DIR" -type f -name '*.php' -not -path "$OUT_DIR/vendor/*")
[ "$LINT_FAIL" -eq 0 ] || exit 1

php -r '
	require $argv[1] . "/vendor/autoload.php";
	$missing = array_filter(
		["Masterminds\\HTML5", "Sabberworm\\CSS\\Parser", "Peast\\Peast"],
		static fn ($c) => ! class_exists($c)
	);
	if ($missing) { fwrite(STDERR, "!! vendor autoload broken: " . implode(", ", $missing) . "\n"); exit(1); }
' "$OUT_DIR"

# WordPress finds the plugin by reading the raw text of the main file, so it must
# stay readable — this is the check that catches an accidental ionCube pass over it.
grep -q 'Plugin Name:' "$OUT_DIR/plugitify.php" || {
	echo "!! plugitify.php has lost its 'Plugin Name:' header" >&2; exit 1; }

echo "    Checks : PHP lint, vendor autoload, plugin header"

# --- Optional archive ---
# Git Bash on Windows has no zip(1), and both Compress-Archive and
# [IO.Compression.ZipFile] on PowerShell 5.1 write Windows backslashes into the
# entry names, which the ZIP spec forbids. PHP's ZipArchive writes forward
# slashes, so unzip and WordPress read the paths correctly.
if [ "$MAKE_ZIP" -eq 1 ]; then
	ZIP_PATH="$ROOT/final/$PLUGIN_SLUG-$VERSION.zip"

	php -r '
		$zip = new ZipArchive();
		if ($zip->open($argv[2], ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
			fwrite(STDERR, "!! could not create the archive\n"); exit(1);
		}
		$items = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($argv[1], FilesystemIterator::SKIP_DOTS),
			RecursiveIteratorIterator::SELF_FIRST
		);
		foreach ($items as $item) {
			$rel = $argv[3] . "/" . str_replace("\\", "/", $items->getSubPathName());
			$ok  = $item->isDir() ? $zip->addEmptyDir($rel) : $zip->addFile($item->getPathname(), $rel);
			if (! $ok) { fwrite(STDERR, "!! could not add " . $rel . "\n"); exit(1); }
		}
		if (! $zip->close()) { fwrite(STDERR, "!! the archive would not close\n"); exit(1); }
	' "$(cygpath -w "$OUT_DIR")" "$(cygpath -w "$ZIP_PATH")" "$PLUGIN_SLUG"

	echo "    Zip    : final/$PLUGIN_SLUG-$VERSION.zip"
fi

FILE_COUNT="$(find "$OUT_DIR" -type f | wc -l | tr -d ' ')"

echo "==> Done."
echo "    Output : $OUT_DIR"
echo "    Files  : $FILE_COUNT"
echo ""
echo "Excluded (not copied): .git, agent (frontend TypeScript sources), landing,"
echo "  build, final, build.sh, composer.json/lock, screenshots and stray root files"
echo ""
echo "Next (rtl-theme.com encryption step):"
echo "  Upload final/$PLUGIN_SLUG/src/App/App.php — it is the file the license"
echo "  code was added to — and replace it with the encoded file you get back."
