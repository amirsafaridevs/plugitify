<?php
namespace Plugitify\muPlugin\Core;

require_once __DIR__ . '/httpException.php';
require_once __DIR__ . '/pluginWorkspace.php';

/**
 * Server-side implementations of the agent's tools.
 *
 * Each public method is one tool. They all return
 *   ['output' => string, 'meta' => array]
 * where 'output' is the text handed straight back to the model and 'meta' is
 * structured detail the chat UI renders (file paths, counts, diff stats).
 *
 * Everything filesystem-related is resolved through PluginWorkspace, so no tool
 * here can read or write outside the plugin directory it was opened for. The
 * two exceptions are deliberate and narrow: read_debug_log reads
 * wp-content/debug.log, and debug_control toggles only the WP_DEBUG* defines in
 * wp-config.php.
 */
class AgentTools
{
    /** Hard cap on how much text a single tool result may return to the model. */
    private const MAX_OUTPUT_CHARS = 60000;

    /** Default number of lines read_file returns when no limit is given. */
    private const DEFAULT_READ_LINES = 800;

    private PluginWorkspace $workspace;

    public function __construct(PluginWorkspace $workspace)
    {
        $this->workspace = $workspace;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Orientation
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Everything the agent needs to know about where it is, in one call.
     *
     * @param array<string, mixed> $args
     * @return array{output:string, meta:array<string, mixed>}
     */
    public function workspace_info(array $args): array
    {
        $slug       = $this->workspace->slug();
        $main       = $this->find_main_plugin_file();
        $tree       = $this->build_tree($this->workspace->root(), 3, 0, $count);
        $pluginData = [];

        if ($main !== null) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
            $pluginData = get_plugin_data($main, false, false);
        }

        $relativeMain = $main !== null ? $this->workspace->relative($main) : null;

        $lines = [
            'Plugin slug: ' . $slug,
            'Plugin directory: wp-content/plugins/' . $slug . '  (this is your entire writable workspace)',
            'Main plugin file: ' . ($relativeMain ?? '(none found — no file has a "Plugin Name:" header yet)'),
            'Plugin Name header: ' . ($pluginData['Name'] ?? '(unset)'),
            'Description: ' . ($pluginData['Description'] ?? '(unset)'),
            'Version: ' . ($pluginData['Version'] ?? '(unset)'),
            'Currently active: ' . ($this->is_plugin_active() ? 'yes' : 'no'),
            'WordPress version: ' . get_bloginfo('version'),
            'PHP version: ' . PHP_VERSION,
            'Site URL: ' . home_url('/'),
            'WP_DEBUG: ' . ((defined('WP_DEBUG') && WP_DEBUG) ? 'on' : 'off'),
            'WP_DEBUG_LOG: ' . ((defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) ? 'on' : 'off'),
            '',
            'File tree (depth 3, ' . $count . ' entries):',
            $tree === '' ? '(the plugin directory is empty)' : $tree,
        ];

        return [
            'output' => implode("\n", $lines),
            'meta'   => [
                'slug'      => $slug,
                'main_file' => $relativeMain,
                'active'    => $this->is_plugin_active(),
                'entries'   => $count,
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Reading
    // ─────────────────────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $args
     * @return array{output:string, meta:array<string, mixed>}
     */
    public function list_files(array $args): array
    {
        $path     = (string) ($args['path'] ?? '');
        $maxDepth = max(1, min(10, (int) ($args['max_depth'] ?? 4)));

        $absolute = $this->workspace->resolve($path);

        if (!is_dir($absolute)) {
            throw new HttpException(404, 'not_a_directory', [], 'Not a directory: ' . ($path === '' ? '.' : $path));
        }

        $count = 0;
        $tree  = $this->build_tree($absolute, $maxDepth, 0, $count);

        return [
            'output' => $tree === ''
                ? 'Directory is empty: ' . $this->workspace->relative($absolute)
                : $this->workspace->relative($absolute) . "/\n" . $tree,
            'meta'   => ['path' => $this->workspace->relative($absolute), 'entries' => $count],
        ];
    }

    /**
     * Read a file as numbered lines, so the model can cite exact line numbers
     * and knows precisely what it is editing.
     *
     * @param array<string, mixed> $args
     * @return array{output:string, meta:array<string, mixed>}
     */
    public function read_file(array $args): array
    {
        $path     = (string) ($args['path'] ?? '');
        $absolute = $this->workspace->resolve($path);
        $relative = $this->workspace->relative($absolute);

        if (!is_file($absolute)) {
            throw new HttpException(404, 'file_not_found', [], 'File not found: ' . $relative);
        }

        if (PluginWorkspace::is_binary_path($absolute)) {
            throw new HttpException(
                422,
                'binary_file',
                [],
                'Refusing to read binary file as text: ' . $relative . ' (' . size_format((int) filesize($absolute)) . ')'
            );
        }

        $size = (int) filesize($absolute);
        if ($size > PluginWorkspace::MAX_FILE_BYTES) {
            throw new HttpException(
                422,
                'file_too_large',
                [],
                'File is too large to read (' . size_format($size) . '). Limit is ' . size_format(PluginWorkspace::MAX_FILE_BYTES) . '.'
            );
        }

        $contents = (string) file_get_contents($absolute);
        $allLines = preg_split('/\r\n|\r|\n/', $contents) ?: [];
        $total    = count($allLines);

        $offset = max(1, (int) ($args['offset'] ?? 1));
        $limit  = (int) ($args['limit'] ?? self::DEFAULT_READ_LINES);
        $limit  = $limit <= 0 ? self::DEFAULT_READ_LINES : $limit;

        $slice = array_slice($allLines, $offset - 1, $limit);

        if ($slice === []) {
            return [
                'output' => $relative . ' has ' . $total . ' lines; offset ' . $offset . ' is past the end.',
                'meta'   => ['path' => $relative, 'total_lines' => $total],
            ];
        }

        $width  = strlen((string) ($offset + count($slice) - 1));
        $body   = [];
        foreach ($slice as $i => $line) {
            $body[] = str_pad((string) ($offset + $i), $width, ' ', STR_PAD_LEFT) . "\t" . $line;
        }

        $header = $relative . ' (lines ' . $offset . '-' . ($offset + count($slice) - 1) . ' of ' . $total . ')';
        $footer = ($offset + count($slice) - 1) < $total
            ? "\n\n... " . ($total - ($offset + count($slice) - 1)) . ' more lines. Call read_file again with a higher offset to continue.'
            : '';

        return [
            'output' => $this->truncate($header . "\n" . implode("\n", $body) . $footer),
            'meta'   => [
                'path'        => $relative,
                'total_lines' => $total,
                'from'        => $offset,
                'to'          => $offset + count($slice) - 1,
            ],
        ];
    }

    /**
     * Regex content search across the workspace — the agent's way of finding
     * where something is defined without reading every file.
     *
     * @param array<string, mixed> $args
     * @return array{output:string, meta:array<string, mixed>}
     */
    public function search_files(array $args): array
    {
        $pattern = (string) ($args['pattern'] ?? '');
        if ($pattern === '') {
            throw new HttpException(422, 'validation_error', [], 'search_files requires a "pattern".');
        }

        $glob       = (string) ($args['glob'] ?? '');
        $maxResults = max(1, min(500, (int) ($args['max_results'] ?? 100)));
        $regex      = '/' . str_replace('/', '\/', $pattern) . '/';
        if (!empty($args['case_insensitive'])) {
            $regex .= 'i';
        }

        // Validate the pattern before walking the tree, so a bad regex reports
        // as a clean validation error rather than a warning storm mid-scan.
        if (@preg_match($regex, '') === false) {
            throw new HttpException(422, 'invalid_regex', [], 'Invalid regular expression: ' . $pattern);
        }

        $hits    = [];
        $matched = 0;
        $files   = 0;

        foreach ($this->walk($this->workspace->root()) as $absolute) {
            if (PluginWorkspace::is_binary_path($absolute) || filesize($absolute) > PluginWorkspace::MAX_FILE_BYTES) {
                continue;
            }

            $relative = $this->workspace->relative($absolute);

            if ($glob !== '' && !fnmatch($glob, $relative) && !fnmatch($glob, basename($relative))) {
                continue;
            }

            $fileHadHit = false;
            $lineNo     = 0;

            foreach (preg_split('/\r\n|\r|\n/', (string) file_get_contents($absolute)) ?: [] as $line) {
                $lineNo++;
                if (preg_match($regex, $line) !== 1) {
                    continue;
                }

                $fileHadHit = true;
                $matched++;

                if (count($hits) < $maxResults) {
                    $hits[] = $relative . ':' . $lineNo . ': ' . trim($line);
                }
            }

            if ($fileHadHit) {
                $files++;
            }
        }

        if ($hits === []) {
            return [
                'output' => 'No matches for /' . $pattern . '/' . ($glob !== '' ? ' in files matching ' . $glob : '') . '.',
                'meta'   => ['matches' => 0, 'files' => 0],
            ];
        }

        $summary = $matched . ' match(es) in ' . $files . ' file(s)'
            . ($matched > count($hits) ? ', showing the first ' . count($hits) : '') . ':';

        return [
            'output' => $this->truncate($summary . "\n" . implode("\n", $hits)),
            'meta'   => ['matches' => $matched, 'files' => $files, 'shown' => count($hits)],
        ];
    }

    /**
     * @param array<string, mixed> $args
     * @return array{output:string, meta:array<string, mixed>}
     */
    public function glob_files(array $args): array
    {
        $pattern = (string) ($args['pattern'] ?? '');
        if ($pattern === '') {
            throw new HttpException(422, 'validation_error', [], 'glob_files requires a "pattern".');
        }

        $found = [];
        foreach ($this->walk($this->workspace->root()) as $absolute) {
            $relative = $this->workspace->relative($absolute);
            if (fnmatch($pattern, $relative) || fnmatch($pattern, basename($relative))) {
                $found[] = $relative;
            }
        }

        sort($found);

        return [
            'output' => $found === []
                ? 'No files match ' . $pattern . '.'
                : $this->truncate(count($found) . ' file(s) matching ' . $pattern . ":\n" . implode("\n", $found)),
            'meta'   => ['pattern' => $pattern, 'count' => count($found)],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Writing
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Create a file or replace it wholesale. Parent directories are created.
     *
     * @param array<string, mixed> $args
     * @return array{output:string, meta:array<string, mixed>}
     */
    public function write_file(array $args): array
    {
        $path = (string) ($args['path'] ?? '');
        if ($path === '') {
            throw new HttpException(422, 'validation_error', [], 'write_file requires a "path".');
        }

        $content  = (string) ($args['content'] ?? '');
        $absolute = $this->workspace->resolve($path);
        $relative = $this->workspace->relative($absolute);

        if (is_dir($absolute)) {
            throw new HttpException(422, 'is_a_directory', [], $relative . ' is a directory, not a file.');
        }

        if (PluginWorkspace::is_binary_path($absolute)) {
            throw new HttpException(422, 'binary_file', [], 'Refusing to write binary file type as text: ' . $relative);
        }

        if (strlen($content) > PluginWorkspace::MAX_FILE_BYTES) {
            throw new HttpException(422, 'file_too_large', [], 'Content exceeds the ' . size_format(PluginWorkspace::MAX_FILE_BYTES) . ' limit.');
        }

        $existed  = is_file($absolute);
        $oldLines = $existed ? substr_count((string) file_get_contents($absolute), "\n") + 1 : 0;

        $parent = \dirname($absolute);
        if (!is_dir($parent) && !wp_mkdir_p($parent)) {
            throw new HttpException(500, 'mkdir_failed', [], 'Could not create directory: ' . $this->workspace->relative($parent));
        }

        if (file_put_contents($absolute, $content) === false) {
            throw new HttpException(500, 'write_failed', [], 'Could not write: ' . $relative);
        }

        $newLines = substr_count($content, "\n") + 1;

        return [
            'output' => ($existed ? 'Overwrote ' : 'Created ') . $relative
                . ' (' . $newLines . ' lines, ' . size_format(strlen($content)) . ')'
                . ($existed ? '. Previous version had ' . $oldLines . ' lines.' : '')
                . $this->lint_hint($absolute),
            'meta'   => [
                'path'      => $relative,
                'action'    => $existed ? 'overwrite' : 'create',
                'lines'     => $newLines,
                'old_lines' => $oldLines,
                'bytes'     => strlen($content),
            ],
        ];
    }

    /**
     * Exact-string replacement. Refuses ambiguous edits: if old_string occurs
     * more than once the agent must either disambiguate with more context or
     * pass replace_all explicitly.
     *
     * @param array<string, mixed> $args
     * @return array{output:string, meta:array<string, mixed>}
     */
    public function edit_file(array $args): array
    {
        $path      = (string) ($args['path'] ?? '');
        $oldString = (string) ($args['old_string'] ?? '');
        $newString = (string) ($args['new_string'] ?? '');
        $replaceAll = !empty($args['replace_all']);

        if ($path === '' || $oldString === '') {
            throw new HttpException(422, 'validation_error', [], 'edit_file requires "path" and a non-empty "old_string".');
        }

        if ($oldString === $newString) {
            throw new HttpException(422, 'validation_error', [], 'old_string and new_string are identical — nothing to change.');
        }

        $absolute = $this->workspace->resolve($path);
        $relative = $this->workspace->relative($absolute);

        if (!is_file($absolute)) {
            throw new HttpException(404, 'file_not_found', [], 'File not found: ' . $relative);
        }

        $contents = (string) file_get_contents($absolute);
        $count    = substr_count($contents, $oldString);

        if ($count === 0) {
            throw new HttpException(
                422,
                'no_match',
                [],
                'old_string was not found in ' . $relative . '. Read the file again — it may have changed, '
                . 'or the whitespace/indentation in old_string may not match exactly.'
            );
        }

        if ($count > 1 && !$replaceAll) {
            throw new HttpException(
                422,
                'ambiguous_match',
                [],
                'old_string occurs ' . $count . ' times in ' . $relative . '. Include more surrounding context to make it '
                . 'unique, or pass replace_all: true to change every occurrence.'
            );
        }

        $updated = $replaceAll
            ? str_replace($oldString, $newString, $contents)
            : $this->replace_first($contents, $oldString, $newString);

        if (file_put_contents($absolute, $updated) === false) {
            throw new HttpException(500, 'write_failed', [], 'Could not write: ' . $relative);
        }

        $replaced = $replaceAll ? $count : 1;
        $lineNo   = $this->line_number_of($contents, $oldString);

        return [
            'output' => 'Edited ' . $relative . ': replaced ' . $replaced . ' occurrence(s)'
                . ($lineNo > 0 ? ' (first at line ' . $lineNo . ')' : '') . '.'
                . $this->lint_hint($absolute),
            'meta'   => [
                'path'         => $relative,
                'action'       => 'edit',
                'replacements' => $replaced,
                'line'         => $lineNo,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $args
     * @return array{output:string, meta:array<string, mixed>}
     */
    public function delete_file(array $args): array
    {
        $path     = (string) ($args['path'] ?? '');
        $absolute = $this->workspace->resolve($path);
        $relative = $this->workspace->relative($absolute);

        if ($absolute === $this->workspace->root()) {
            throw new HttpException(403, 'forbidden', [], 'Refusing to delete the plugin root.');
        }

        if (is_dir($absolute)) {
            throw new HttpException(422, 'is_a_directory', [], $relative . ' is a directory — use delete_directory.');
        }

        if (!is_file($absolute)) {
            throw new HttpException(404, 'file_not_found', [], 'File not found: ' . $relative);
        }

        if (!unlink($absolute)) {
            throw new HttpException(500, 'delete_failed', [], 'Could not delete: ' . $relative);
        }

        return [
            'output' => 'Deleted ' . $relative . '.',
            'meta'   => ['path' => $relative, 'action' => 'delete'],
        ];
    }

    /**
     * @param array<string, mixed> $args
     * @return array{output:string, meta:array<string, mixed>}
     */
    public function create_directory(array $args): array
    {
        $path     = (string) ($args['path'] ?? '');
        $absolute = $this->workspace->resolve($path);
        $relative = $this->workspace->relative($absolute);

        if (is_dir($absolute)) {
            return [
                'output' => 'Directory already exists: ' . $relative,
                'meta'   => ['path' => $relative, 'action' => 'noop'],
            ];
        }

        if (is_file($absolute)) {
            throw new HttpException(422, 'is_a_file', [], $relative . ' already exists as a file.');
        }

        if (!wp_mkdir_p($absolute)) {
            throw new HttpException(500, 'mkdir_failed', [], 'Could not create directory: ' . $relative);
        }

        return [
            'output' => 'Created directory ' . $relative . '.',
            'meta'   => ['path' => $relative, 'action' => 'mkdir'],
        ];
    }

    /**
     * @param array<string, mixed> $args
     * @return array{output:string, meta:array<string, mixed>}
     */
    public function delete_directory(array $args): array
    {
        $path      = (string) ($args['path'] ?? '');
        $recursive = !empty($args['recursive']);
        $absolute  = $this->workspace->resolve($path);
        $relative  = $this->workspace->relative($absolute);

        if ($absolute === $this->workspace->root()) {
            throw new HttpException(
                403,
                'forbidden',
                [],
                'Refusing to delete the plugin root. Deleting the whole plugin is the user\'s job, not yours.'
            );
        }

        if (!is_dir($absolute)) {
            throw new HttpException(404, 'directory_not_found', [], 'Directory not found: ' . $relative);
        }

        $removed = $this->remove_directory($absolute, $recursive);

        return [
            'output' => 'Deleted directory ' . $relative . ' (' . $removed . ' entries removed).',
            'meta'   => ['path' => $relative, 'action' => 'rmdir', 'removed' => $removed],
        ];
    }

    /**
     * @param array<string, mixed> $args
     * @return array{output:string, meta:array<string, mixed>}
     */
    public function move_path(array $args): array
    {
        $from = (string) ($args['from'] ?? '');
        $to   = (string) ($args['to'] ?? '');

        if ($from === '' || $to === '') {
            throw new HttpException(422, 'validation_error', [], 'move_path requires "from" and "to".');
        }

        $fromAbs = $this->workspace->resolve($from);
        $toAbs   = $this->workspace->resolve($to);

        if ($fromAbs === $this->workspace->root()) {
            throw new HttpException(403, 'forbidden', [], 'Refusing to move the plugin root.');
        }

        if (!file_exists($fromAbs)) {
            throw new HttpException(404, 'not_found', [], 'Source not found: ' . $this->workspace->relative($fromAbs));
        }

        if (file_exists($toAbs)) {
            throw new HttpException(422, 'destination_exists', [], 'Destination already exists: ' . $this->workspace->relative($toAbs));
        }

        $parent = \dirname($toAbs);
        if (!is_dir($parent) && !wp_mkdir_p($parent)) {
            throw new HttpException(500, 'mkdir_failed', [], 'Could not create destination directory.');
        }

        if (!rename($fromAbs, $toAbs)) {
            throw new HttpException(500, 'move_failed', [], 'Could not move ' . $from . ' to ' . $to . '.');
        }

        return [
            'output' => 'Moved ' . $this->workspace->relative($fromAbs) . ' to ' . $this->workspace->relative($toAbs) . '.',
            'meta'   => [
                'from'   => $this->workspace->relative($fromAbs),
                'to'     => $this->workspace->relative($toAbs),
                'action' => 'move',
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Verification & WordPress diagnostics
    // ─────────────────────────────────────────────────────────────────────

    /**
     * `php -l` over one file or every PHP file in the workspace. This is the
     * agent's cheapest safety net: a parse error here would be a white screen
     * of death the moment the plugin is activated.
     *
     * @param array<string, mixed> $args
     * @return array{output:string, meta:array<string, mixed>}
     */
    public function php_lint(array $args): array
    {
        $binary = $this->php_binary();
        if ($binary === null) {
            return [
                'output' => 'php_lint is unavailable on this server (no reachable PHP CLI binary, or exec() is disabled). '
                    . 'Review the syntax manually instead.',
                'meta'   => ['available' => false],
            ];
        }

        $path    = (string) ($args['path'] ?? '');
        $targets = [];

        if ($path !== '') {
            $absolute = $this->workspace->resolve($path);
            if (!is_file($absolute)) {
                throw new HttpException(404, 'file_not_found', [], 'File not found: ' . $this->workspace->relative($absolute));
            }
            $targets[] = $absolute;
        } else {
            foreach ($this->walk($this->workspace->root()) as $absolute) {
                if (strtolower((string) pathinfo($absolute, PATHINFO_EXTENSION)) === 'php') {
                    $targets[] = $absolute;
                }
            }
        }

        if ($targets === []) {
            return ['output' => 'No PHP files to lint.', 'meta' => ['checked' => 0, 'errors' => 0]];
        }

        $failures = [];
        foreach ($targets as $absolute) {
            $output = [];
            $status = 0;
            // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- running `php -l` is the whole point of this tool; the argument is a workspace-contained path escaped with escapeshellarg().
            exec(escapeshellarg($binary) . ' -l ' . escapeshellarg($absolute) . ' 2>&1', $output, $status);

            if ($status !== 0) {
                $message = implode(' ', $output);
                $message = str_replace([$absolute, str_replace('/', '\\', $absolute)], $this->workspace->relative($absolute), $message);
                $failures[] = $message;
            }
        }

        if ($failures === []) {
            return [
                'output' => 'Syntax OK — ' . count($targets) . ' PHP file(s) checked, no errors.',
                'meta'   => ['checked' => count($targets), 'errors' => 0, 'available' => true],
            ];
        }

        return [
            'output' => $this->truncate(
                count($failures) . ' of ' . count($targets) . ' file(s) have syntax errors. FIX THESE BEFORE CONTINUING:' . "\n"
                . implode("\n", $failures)
            ),
            'meta'   => ['checked' => count($targets), 'errors' => count($failures), 'available' => true],
        ];
    }

    /**
     * Activate / deactivate / inspect the plugin under construction.
     *
     * Activation lints first: activate_plugin() includes the plugin file, so a
     * parse error there would fatal the request instead of returning an error.
     *
     * @param array<string, mixed> $args
     * @return array{output:string, meta:array<string, mixed>}
     */
    public function plugin_control(array $args): array
    {
        $action = (string) ($args['action'] ?? 'status');
        $main   = $this->find_main_plugin_file();

        if ($main === null) {
            throw new HttpException(
                422,
                'no_main_file',
                [],
                'No file in this plugin has a "Plugin Name:" header yet, so WordPress does not recognise it as a plugin. '
                . 'Create the main plugin file first.'
            );
        }

        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        $pluginFile = $this->workspace->slug() . '/' . basename($main);

        if ($action === 'status') {
            return [
                'output' => 'Plugin ' . $pluginFile . ' is currently ' . ($this->is_plugin_active() ? 'ACTIVE' : 'INACTIVE') . '.',
                'meta'   => ['action' => 'status', 'active' => $this->is_plugin_active()],
            ];
        }

        if ($action === 'deactivate') {
            deactivate_plugins([$pluginFile], true);

            return [
                'output' => 'Deactivated ' . $pluginFile . '.',
                'meta'   => ['action' => 'deactivate', 'active' => false],
            ];
        }

        if ($action !== 'activate') {
            throw new HttpException(422, 'validation_error', [], 'plugin_control action must be status, activate, or deactivate.');
        }

        $lint = $this->php_lint([]);
        if (($lint['meta']['errors'] ?? 0) > 0) {
            throw new HttpException(
                422,
                'lint_failed',
                [],
                'Refusing to activate: the plugin has PHP syntax errors and activating it would take the site down.' . "\n"
                . $lint['output']
            );
        }

        $result = activate_plugin($pluginFile, '', false, true);

        if (is_wp_error($result)) {
            throw new HttpException(500, 'activation_failed', [], 'Activation failed: ' . $result->get_error_message());
        }

        return [
            'output' => 'Activated ' . $pluginFile . '. Reload the preview to see it running.',
            'meta'   => ['action' => 'activate', 'active' => true],
        ];
    }

    /**
     * Read or toggle the WP_DEBUG* constants in wp-config.php.
     *
     * This is the one tool that writes outside the workspace, so it is
     * deliberately narrow: it only ever rewrites the three WP_DEBUG defines
     * (adding them before the "stop editing" marker if absent) and it backs the
     * file up first.
     *
     * @param array<string, mixed> $args
     * @return array{output:string, meta:array<string, mixed>}
     */
    public function debug_control(array $args): array
    {
        $action = (string) ($args['action'] ?? 'status');
        $config = $this->wp_config_path();

        if ($action === 'status' || $config === null) {
            $lines = [
                'WP_DEBUG: ' . ((defined('WP_DEBUG') && WP_DEBUG) ? 'on' : 'off'),
                'WP_DEBUG_LOG: ' . ((defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) ? 'on' : 'off'),
                'WP_DEBUG_DISPLAY: ' . ((defined('WP_DEBUG_DISPLAY') && WP_DEBUG_DISPLAY) ? 'on' : 'off'),
                'Log file: ' . $this->debug_log_path() . (is_file($this->debug_log_path()) ? '' : ' (does not exist yet)'),
            ];

            if ($config === null && $action !== 'status') {
                $lines[] = '';
                $lines[] = 'wp-config.php is not writable, so debug settings cannot be changed from here.';
            }

            return ['output' => implode("\n", $lines), 'meta' => ['action' => 'status']];
        }

        if ($action !== 'enable' && $action !== 'disable') {
            throw new HttpException(422, 'validation_error', [], 'debug_control action must be status, enable, or disable.');
        }

        $enable   = $action === 'enable';
        $contents = (string) file_get_contents($config);
        $original = $contents;

        $wanted = [
            'WP_DEBUG'         => $enable,
            'WP_DEBUG_LOG'     => $enable,
            // Display stays off even when debugging: notices printed into the
            // page body break redirects and JSON responses.
            'WP_DEBUG_DISPLAY' => false,
        ];

        foreach ($wanted as $constant => $value) {
            $literal = $value ? 'true' : 'false';
            $define  = "define( '" . $constant . "', " . $literal . " );";
            $pattern = "/^[ \t]*define\(\s*(['\"])" . $constant . "\\1\s*,\s*[^)]*\)\s*;.*$/mi";

            if (preg_match($pattern, $contents) === 1) {
                $contents = (string) preg_replace($pattern, $define, $contents, 1);
                continue;
            }

            // Not present — insert above the "stop editing" marker, which is
            // where WordPress expects user constants to live.
            $marker = "/^\/\*.*stop editing.*\*\/$/mi";
            if (preg_match($marker, $contents) === 1) {
                $contents = (string) preg_replace($marker, $define . "\n\n$0", $contents, 1);
            } else {
                $contents = (string) preg_replace("/^<\?php$/m", "<?php\n" . $define, $contents, 1);
            }
        }

        if ($contents === $original) {
            return [
                'output' => 'Debug settings already match the requested state (' . $action . ').',
                'meta'   => ['action' => $action, 'changed' => false],
            ];
        }

        if (!copy($config, $config . '.plugitify-bak')) {
            throw new HttpException(500, 'backup_failed', [], 'Could not back up wp-config.php; aborting rather than editing it unbacked.');
        }

        if (file_put_contents($config, $contents) === false) {
            throw new HttpException(500, 'write_failed', [], 'Could not write wp-config.php.');
        }

        return [
            'output' => 'Debug logging ' . ($enable ? 'ENABLED' : 'DISABLED') . ' in wp-config.php'
                . ($enable ? ' (WP_DEBUG on, WP_DEBUG_LOG on, WP_DEBUG_DISPLAY off).' : '.')
                . ' The change takes effect on the next request — reload the preview, then call read_debug_log.',
            'meta'   => ['action' => $action, 'changed' => true],
        ];
    }

    /**
     * Tail (and optionally clear) wp-content/debug.log.
     *
     * @param array<string, mixed> $args
     * @return array{output:string, meta:array<string, mixed>}
     */
    public function read_debug_log(array $args): array
    {
        $log = $this->debug_log_path();

        if (!empty($args['clear'])) {
            if (is_file($log) && file_put_contents($log, '') === false) {
                throw new HttpException(500, 'clear_failed', [], 'Could not clear the debug log.');
            }

            return [
                'output' => 'Debug log cleared. Reproduce the problem, then read it again to see only the fresh entries.',
                'meta'   => ['action' => 'clear'],
            ];
        }

        if (!is_file($log)) {
            return [
                'output' => 'No debug log at ' . $log . '. Either nothing has been logged yet, or WP_DEBUG_LOG is off '
                    . '— call debug_control with action "enable" to turn it on.',
                'meta'   => ['exists' => false],
            ];
        }

        $wanted   = max(1, min(2000, (int) ($args['lines'] ?? 200)));
        $filter   = (string) ($args['filter'] ?? '');
        $allLines = preg_split('/\r\n|\r|\n/', (string) file_get_contents($log)) ?: [];

        if ($filter !== '') {
            $allLines = array_values(array_filter($allLines, static function ($line) use ($filter) {
                return stripos($line, $filter) !== false;
            }));
        }

        $tail = array_slice($allLines, -$wanted);
        $tail = array_values(array_filter($tail, static fn ($line) => trim($line) !== ''));

        if ($tail === []) {
            return [
                'output' => 'Debug log is empty' . ($filter !== '' ? ' (after filtering for "' . $filter . '")' : '') . '.',
                'meta'   => ['exists' => true, 'lines' => 0],
            ];
        }

        return [
            'output' => $this->truncate(
                'Last ' . count($tail) . ' line(s) of debug.log'
                . ($filter !== '' ? ' matching "' . $filter . '"' : '') . ":\n" . implode("\n", $tail)
            ),
            'meta'   => ['exists' => true, 'lines' => count($tail)],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Internals
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Depth-limited ASCII tree. $count accumulates the number of entries shown.
     */
    private function build_tree(string $dir, int $maxDepth, int $depth, ?int &$count = null): string
    {
        if ($count === null) {
            $count = 0;
        }

        if ($depth >= $maxDepth) {
            return '';
        }

        $entries = @scandir($dir);
        if ($entries === false) {
            return '';
        }

        $entries = array_values(array_filter($entries, static fn ($e) => $e !== '.' && $e !== '..'));
        sort($entries);

        $lines  = [];
        $indent = str_repeat('  ', $depth + 1);

        foreach ($entries as $entry) {
            $absolute = $dir . '/' . $entry;

            if (is_dir($absolute)) {
                $count++;

                if (PluginWorkspace::is_ignored_dir($entry)) {
                    $lines[] = $indent . $entry . '/  (skipped)';
                    continue;
                }

                $lines[] = $indent . $entry . '/';
                $child   = $this->build_tree($absolute, $maxDepth, $depth + 1, $count);
                if ($child !== '') {
                    $lines[] = $child;
                }

                continue;
            }

            $count++;
            $lines[] = $indent . $entry . '  (' . size_format((int) filesize($absolute)) . ')';
        }

        return implode("\n", $lines);
    }

    /**
     * Yield every file in the workspace, skipping ignored directories.
     *
     * @return \Generator<string>
     */
    private function walk(string $dir): \Generator
    {
        $entries = @scandir($dir);
        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $absolute = $dir . '/' . $entry;

            if (is_dir($absolute)) {
                if (PluginWorkspace::is_ignored_dir($entry)) {
                    continue;
                }

                yield from $this->walk($absolute);

                continue;
            }

            yield $absolute;
        }
    }

    private function remove_directory(string $dir, bool $recursive): int
    {
        $entries = array_values(array_filter(@scandir($dir) ?: [], static fn ($e) => $e !== '.' && $e !== '..'));

        if ($entries !== [] && !$recursive) {
            throw new HttpException(
                422,
                'directory_not_empty',
                [],
                $this->workspace->relative($dir) . ' is not empty (' . count($entries) . ' entries). '
                . 'Pass recursive: true if you really mean to delete its contents.'
            );
        }

        $removed = 0;

        foreach ($entries as $entry) {
            $absolute = $dir . '/' . $entry;

            if (is_dir($absolute)) {
                $removed += $this->remove_directory($absolute, true);
                continue;
            }

            if (unlink($absolute)) {
                $removed++;
            }
        }

        if (!rmdir($dir)) {
            throw new HttpException(500, 'rmdir_failed', [], 'Could not remove directory: ' . $this->workspace->relative($dir));
        }

        return $removed + 1;
    }

    /**
     * After any PHP write, lint that one file and fold the result into the tool
     * output — the agent finds out about a parse error immediately instead of
     * discovering it later via a white screen.
     */
    private function lint_hint(string $absolute): string
    {
        if (strtolower((string) pathinfo($absolute, PATHINFO_EXTENSION)) !== 'php') {
            return '';
        }

        $binary = $this->php_binary();
        if ($binary === null) {
            return '';
        }

        $output = [];
        $status = 0;
        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- syntax-checking the file just written; the path is workspace-contained and escaped.
        exec(escapeshellarg($binary) . ' -l ' . escapeshellarg($absolute) . ' 2>&1', $output, $status);

        if ($status === 0) {
            return ' Syntax OK.';
        }

        $message = implode(' ', $output);
        $message = str_replace([$absolute, str_replace('/', '\\', $absolute)], $this->workspace->relative($absolute), $message);

        return ' *** PHP SYNTAX ERROR — fix this now: ' . $message . ' ***';
    }

    /**
     * Locate a PHP CLI binary. Under mod_php, PHP_BINARY points at Apache, so
     * fall back to PHP_BINDIR and then to whatever is on PATH.
     */
    private function php_binary(): ?string
    {
        static $resolved = false;
        static $binary   = null;

        if ($resolved) {
            return $binary;
        }

        $resolved = true;

        if (!function_exists('exec')) {
            return null;
        }

        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        if (in_array('exec', $disabled, true)) {
            return null;
        }

        $isWindows  = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        $executable = $isWindows ? 'php.exe' : 'php';

        $candidates = [];

        if (defined('PHP_BINARY') && PHP_BINARY !== '' && basename(PHP_BINARY) === $executable) {
            $candidates[] = PHP_BINARY;
        }

        if (defined('PHP_BINDIR') && PHP_BINDIR !== '') {
            $candidates[] = PHP_BINDIR . '/' . $executable;
        }

        $candidates[] = $executable;

        foreach ($candidates as $candidate) {
            $output = [];
            $status = 0;
            // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- probing a fixed set of PHP binary paths to find a usable CLI for `php -l`.
            exec(escapeshellarg($candidate) . ' -v 2>&1', $output, $status);

            if ($status === 0 && stripos(implode(' ', $output), 'php') !== false) {
                $binary = $candidate;

                return $binary;
            }
        }

        return null;
    }

    /**
     * The plugin's main file: the first PHP file in the root carrying a
     * "Plugin Name:" header, preferring {slug}.php.
     */
    private function find_main_plugin_file(): ?string
    {
        $root      = $this->workspace->root();
        $preferred = $root . '/' . $this->workspace->slug() . '.php';

        $candidates = is_file($preferred) ? [$preferred] : [];

        foreach (glob($root . '/*.php') ?: [] as $file) {
            if ($file !== $preferred) {
                $candidates[] = $file;
            }
        }

        foreach ($candidates as $file) {
            $head = (string) file_get_contents($file, false, null, 0, 8192);
            if (preg_match('/^[ \t\/*#@]*Plugin Name:/mi', $head) === 1) {
                return $file;
            }
        }

        return null;
    }

    private function is_plugin_active(): bool
    {
        $active = get_option('active_plugins', []);
        if (!is_array($active)) {
            return false;
        }

        $prefix = $this->workspace->slug() . '/';

        foreach ($active as $entry) {
            if (is_string($entry) && strpos($entry, $prefix) === 0) {
                return true;
            }
        }

        return false;
    }

    private function wp_config_path(): ?string
    {
        foreach ([ABSPATH . 'wp-config.php', \dirname(ABSPATH) . '/wp-config.php'] as $candidate) {
            if (is_file($candidate) && is_writable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function debug_log_path(): string
    {
        if (defined('WP_DEBUG_LOG') && is_string(WP_DEBUG_LOG) && WP_DEBUG_LOG !== '') {
            return WP_DEBUG_LOG;
        }

        return WP_CONTENT_DIR . '/debug.log';
    }

    private function replace_first(string $haystack, string $needle, string $replacement): string
    {
        $position = strpos($haystack, $needle);

        return $position === false
            ? $haystack
            : substr_replace($haystack, $replacement, $position, strlen($needle));
    }

    private function line_number_of(string $haystack, string $needle): int
    {
        $position = strpos($haystack, $needle);

        return $position === false ? 0 : substr_count(substr($haystack, 0, $position), "\n") + 1;
    }

    private function truncate(string $text): string
    {
        if (strlen($text) <= self::MAX_OUTPUT_CHARS) {
            return $text;
        }

        return substr($text, 0, self::MAX_OUTPUT_CHARS)
            . "\n\n[output truncated at " . self::MAX_OUTPUT_CHARS . ' characters — narrow your query to see the rest]';
    }
}
