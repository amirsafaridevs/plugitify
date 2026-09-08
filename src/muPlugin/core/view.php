<?php
namespace Plugitify\muPlugin\Core;

/**
 * Static helper for rendering muPlugin views and their CSS/JS assets.
 *
 * Views live in src/muPlugin/view/{name}.php
 * Assets live in  src/muPlugin/view/assets/css/{name} and assets/js/{name}
 */
class View
{
    private const ASSET_URL_BASE = 'plugins/plugitify/src/muPlugin/view/assets';

    /**
     * Render a view file to a string.
     *
     * @param string $view Basename of the view file (without .php), e.g. "studio".
     * @param array<string, mixed> $data Variables to extract into the view's scope.
     */
    public static function render(string $view, array $data = []): string
    {
        $view_path = self::view_dir() . '/' . $view . '.php';

        if (!is_file($view_path)) {
            return '';
        }

        extract($data, EXTR_SKIP);

        ob_start();
        require $view_path;

        return ob_get_clean() ?: '';
    }

    /**
     * Build a <link> tag for a CSS asset under assets/css/.
     */
    public static function css(string $name): string
    {
        $url = self::asset_url('css', $name);

        // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- this is a standalone page rendered outside the theme pipeline (no wp_head()/wp_enqueue_scripts action fires here), so there is no enqueue hook to attach to.
        return $url === '' ? '' : '<link rel="stylesheet" href="' . esc_url($url) . '">';
    }

    /**
     * Build a <script> tag for a JS asset under assets/js/.
     */
    public static function js(string $name): string
    {
        $url = self::asset_url('js', $name);

        // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- this is a standalone page rendered outside the theme pipeline (no wp_footer()/wp_enqueue_scripts action fires here), so there is no enqueue hook to attach to.
        return $url === '' ? '' : '<script type="module" src="' . esc_url($url) . '"></script>';
    }

    /**
     * Resolve the public, cache-busted URL of an asset under assets/{type}/.
     */
    public static function asset_url(string $type, string $name): string
    {
        $path = self::view_dir() . '/assets/' . $type . '/' . $name;

        if (!is_file($path)) {
            return '';
        }

        return content_url(self::ASSET_URL_BASE . '/' . $type . '/' . $name) . '?v=' . filemtime($path);
    }

    private static function view_dir(): string
    {
        return dirname(__DIR__) . '/view';
    }
}
