<?php
namespace Plugitify\muPlugin\Core;

require_once __DIR__ . '/httpException.php';

/**
 * The agent's sandbox: a single plugin directory, identified by its slug.
 *
 * Every path an agent tool touches goes through resolve() first. Escaping the
 * workspace is blocked twice over: lexically (no "..", no absolute paths, no
 * drive letters, no null bytes) and then physically (the nearest existing
 * ancestor is realpath()'d and must still sit inside the workspace root, which
 * catches symlinks pointing out of the tree).
 *
 * Plugitify's own directory is never a valid workspace — the agent must not be
 * able to rewrite the tooling that is running it.
 */
class PluginWorkspace
{
    /** Largest file the agent may read or write, in bytes. */
    public const MAX_FILE_BYTES = 1572864; // 1.5 MB

    /** Directories skipped when listing/searching — noise the agent never needs. */
    private const IGNORED_DIRS = ['.git', 'node_modules', 'vendor', '.svn', '.idea', '.vscode'];

    /** Extensions treated as binary: listable, never read or written as text. */
    private const BINARY_EXTENSIONS = [
        'png', 'jpg', 'jpeg', 'gif', 'webp', 'ico', 'bmp', 'avif', 'tiff',
        'woff', 'woff2', 'ttf', 'eot', 'otf',
        'zip', 'gz', 'tar', 'rar', '7z', 'phar',
        'pdf', 'mp3', 'mp4', 'webm', 'ogg', 'wav', 'mov',
        'exe', 'dll', 'so', 'dylib', 'bin', 'sqlite', 'db',
    ];

    private string $slug;

    private string $root;

    /**
     * @throws HttpException When the slug is malformed, reserved, or has no directory.
     */
    public function __construct(string $slug)
    {
        if (!self::is_valid_slug($slug)) {
            throw new HttpException(422, 'invalid_slug', [], 'Invalid plugin slug: ' . $slug);
        }

        if ($slug === 'plugitify') {
            throw new HttpException(403, 'forbidden_workspace', [], 'Plugitify cannot be used as a workspace.');
        }

        $root = realpath(WP_PLUGIN_DIR . '/' . $slug);
        if ($root === false || !is_dir($root)) {
            throw new HttpException(404, 'workspace_not_found', [], 'Plugin directory not found for slug: ' . $slug);
        }

        $this->slug = $slug;
        $this->root = self::normalize_separators($root);
    }

    public static function is_valid_slug(string $slug): bool
    {
        return $slug !== '' && preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug) === 1;
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function root(): string
    {
        return $this->root;
    }

    /**
     * Turn a workspace-relative path into a validated absolute path.
     *
     * The path does not need to exist (that is how new files get created), but
     * whatever part of it does exist must resolve inside the workspace.
     *
     * @param string $relative Path relative to the workspace root. '' or '.' means the root itself.
     * @throws HttpException 422 on a malformed path, 403 on an escape attempt.
     */
    public function resolve(string $relative): string
    {
        $relative = trim($relative);

        if (strpos($relative, "\0") !== false) {
            throw new HttpException(422, 'invalid_path', [], 'Path contains a null byte.');
        }

        $relative = self::normalize_separators($relative);

        if ($relative === '' || $relative === '.' || $relative === './') {
            return $this->root;
        }

        // Absolute paths are always rejected — tools speak in relative paths only.
        if ($relative[0] === '/' || preg_match('#^[A-Za-z]:#', $relative) === 1) {
            throw new HttpException(
                403,
                'path_outside_workspace',
                [],
                'Absolute paths are not allowed. Use a path relative to the plugin root.'
            );
        }

        $segments = [];
        foreach (explode('/', $relative) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                throw new HttpException(
                    403,
                    'path_outside_workspace',
                    [],
                    'Path traversal ("..") is not allowed. Tools may only touch files inside the "' . $this->slug . '" plugin.'
                );
            }

            $segments[] = $segment;
        }

        if ($segments === []) {
            return $this->root;
        }

        $absolute = $this->root . '/' . implode('/', $segments);

        $this->assert_inside($absolute);

        return $absolute;
    }

    /**
     * Physical containment check: walk up to the nearest ancestor that exists,
     * resolve it through realpath(), and require it to still be under the root.
     * This is what stops a symlink inside the workspace from pointing outside it.
     *
     * @throws HttpException
     */
    private function assert_inside(string $absolute): void
    {
        $existing = $absolute;
        while ($existing !== '' && !file_exists($existing)) {
            $parent = \dirname($existing);
            if ($parent === $existing) {
                break;
            }
            $existing = $parent;
        }

        $real = realpath($existing);
        if ($real === false) {
            throw new HttpException(403, 'path_outside_workspace', [], 'Path could not be resolved inside the workspace.');
        }

        $real = self::normalize_separators($real);

        if ($real !== $this->root && strpos($real, $this->root . '/') !== 0) {
            throw new HttpException(
                403,
                'path_outside_workspace',
                [],
                'Resolved path escapes the "' . $this->slug . '" plugin directory.'
            );
        }
    }

    /**
     * Absolute path -> the workspace-relative path shown to the agent.
     */
    public function relative(string $absolute): string
    {
        $absolute = self::normalize_separators($absolute);

        if ($absolute === $this->root) {
            return '.';
        }

        if (strpos($absolute, $this->root . '/') === 0) {
            return substr($absolute, strlen($this->root) + 1);
        }

        return $absolute;
    }

    public static function is_ignored_dir(string $name): bool
    {
        return in_array($name, self::IGNORED_DIRS, true);
    }

    public static function is_binary_path(string $path): bool
    {
        $ext = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

        return $ext !== '' && in_array($ext, self::BINARY_EXTENSIONS, true);
    }

    /**
     * Windows gives back backslashes from realpath(); every comparison in this
     * class assumes forward slashes, so normalize once at every entry point.
     */
    private static function normalize_separators(string $path): string
    {
        $path = str_replace('\\', '/', $path);

        return rtrim($path, '/') === '' ? $path : rtrim($path, '/');
    }
}
