<?php

namespace Plugifity\Service\Tools;

if (!defined('ABSPATH')) {
    exit;
}

use Plugifity\Contract\Abstract\AbstractService;
use Plugifity\Core\Http\ApiRouter;
use Plugifity\Core\Http\Request;
use Plugifity\Core\Http\Response;
use Plugifity\Helper\RecordBuffer;

/**
 * API Tools – File service (grep, list dir, read/write file, etc.).
 */
class File extends AbstractService
{
    private const API_SOURCE = 'api.file';

    /**
     * Record file API call and return buffer for optional log/change and save.
     *
     * @param Request $request
     * @param string  $endpoint Endpoint path (e.g. 'file/grep').
     * @param string  $title    Short title for the action.
     * @param array   $details  Optional details (path, params, etc.).
     * @return RecordBuffer
     */
    private function recordFileApi(Request $request, string $endpoint, string $title, array $details = []): RecordBuffer
    {
        $buffer = RecordBuffer::get();
        $buffer->addApiRequest(
            $endpoint,
            $title,
            null,
            self::API_SOURCE,
            $details !== [] ? wp_json_encode($details) : null
        );
        return $buffer;
    }

    /**
     * Boot the service – register file API routes.
     *
     * @return void
     */
    public function boot(): void
    {
        ApiRouter::post('file/grep', [$this, 'grep'])->name('api.tools.file.grep');
        ApiRouter::post('file/list-directory', [$this, 'listDirectory'])->name('api.tools.file.list');
        ApiRouter::post('file/wp-path', [$this, 'wpPath'])->name('api.tools.file.wp-path');
        ApiRouter::post('file/read', [$this, 'readFile'])->name('api.tools.file.read');
        ApiRouter::post('file/create', [$this, 'createFile'])->name('api.tools.file.create');
        ApiRouter::post('file/create-folder', [$this, 'createFolder'])->name('api.tools.file.create-folder');
        ApiRouter::post('file/replace-content', [$this, 'replaceContent'])->name('api.tools.file.replace-content');
        ApiRouter::post('file/replace-line', [$this, 'replaceLine'])->name('api.tools.file.replace-line');
        ApiRouter::post('file/delete', [$this, 'delete'])->name('api.tools.file.delete');
    }

    /**
     * Resolve path to be under WordPress root (ABSPATH).
     * Returns array with 'resolved' (string|null) and 'debug' (base, input, attempted) for precise error messages.
     *
     * @param string $path Path (relative to ABSPATH or absolute).
     * @return array{resolved: string|null, debug: array{base: string, input: string, attempted: string}}
     */
    /**
     * Build path in OS-native form (backslash on Windows, slash on Linux).
     * Uses DIRECTORY_SEPARATOR so realpath/file_exists work on both.
     */
    private function resolvePath(string $path): array
    {
        $path = trim($path);
        $base = rtrim(ABSPATH, '/\\');
        $baseReal = realpath($base);
        if ($baseReal === false) {
            $baseReal = $base;
        }
        $debug = [
            'base'      => $baseReal,
            'input'     => $path,
            'attempted' => '',
        ];
        if ($path === '') {
            return ['resolved' => null, 'debug' => $debug];
        }
        $pathWithForwardSlash = str_replace('\\', '/', $path);
        $isAbsolute = $this->pathIsAbsolute($pathWithForwardSlash);
        $pathRelativePart = $isAbsolute ? $pathWithForwardSlash : ltrim($pathWithForwardSlash, '/');
        $pathNative = str_replace('/', DIRECTORY_SEPARATOR, $pathRelativePart);
        if ($isAbsolute) {
            $combined = $pathNative;
        } else {
            $combined = $baseReal . DIRECTORY_SEPARATOR . $pathNative;
        }
        $debug['attempted'] = $combined;
        $resolved = @realpath($combined);
        if ($resolved !== false) {
            $ok = $this->pathIsInside($resolved, $baseReal);
            return ['resolved' => $ok ? $resolved : null, 'debug' => $debug];
        }
        $normalized = $this->normalizePath($combined);
        $debug['attempted'] = $combined . ' (realpath failed; normalized: ' . ($normalized ?? 'null') . ')';
        $ok = $normalized !== null && $this->pathIsInside($normalized, $baseReal);
        return ['resolved' => $ok ? $normalized : null, 'debug' => $debug];
    }

    /** Windows: drive letter (C:). Linux/Unix: leading /. */
    private function pathIsAbsolute(string $pathForwardSlash): bool
    {
        $pathForwardSlash = trim($pathForwardSlash);
        if ($pathForwardSlash === '') {
            return false;
        }
        if (strlen($pathForwardSlash) > 1 && $pathForwardSlash[1] === ':') {
            return true;
        }
        return $pathForwardSlash[0] === '/';
    }

    /**
     * Check if path is inside base. Windows: case-insensitive. Linux: case-sensitive.
     *
     * @param string $path Resolved path (OS-native).
     * @param string $base WordPress root (OS-native).
     * @return bool
     */
    private function pathIsInside(string $path, string $base): bool
    {
        $path = rtrim($path, '/\\');
        $base = rtrim($base, '/\\');
        $baseWithSep = $base . DIRECTORY_SEPARATOR;
        if (DIRECTORY_SEPARATOR === '\\') {
            return str_starts_with(strtolower($path), strtolower($baseWithSep))
                || strtolower($path) === strtolower($base);
        }
        return str_starts_with($path, $baseWithSep) || $path === $base;
    }

    /**
     * Normalize path (resolve . and ..) without requiring path to exist.
     *
     * @param string $path
     * @return string|null
     */
    private function normalizePath(string $path): ?string
    {
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        $leading = (strlen($path) > 0 && $path[0] === DIRECTORY_SEPARATOR) ? DIRECTORY_SEPARATOR : '';
        $parts = [];
        foreach (explode(DIRECTORY_SEPARATOR, $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                if ($parts === []) {
                    return null;
                }
                array_pop($parts);
                continue;
            }
            $parts[] = $segment;
        }
        return $leading . implode(DIRECTORY_SEPARATOR, $parts);
    }

    /**
     * Grep: search for pattern in files under a directory (like Cursor grep).
     * Input: path (directory), pattern (string; if regex=true, pattern is PCRE), case_sensitive (optional), regex (optional bool).
     *
     * @param Request $request
     * @return array{success: bool, message: string, data: mixed}
     */
    public function grep(Request $request): array
    {
        $path    = $request->str('path', '');
        $pattern = $request->str('pattern', '');
        $buffer  = $this->recordFileApi($request, 'file/grep', __('Grep files', 'plugitify'), [
            'path'    => $path,
            'pattern' => $pattern,
        ]);

        if ($path === '' || $pattern === '') {
            $buffer->addLog('error', __('path and pattern are required.', 'plugitify'));
            $buffer->save();
            return Response::error(__('path and pattern are required.', 'plugitify'));
        }
        $result = $this->resolvePath($path);
        $dir    = $result['resolved'];
        if ($dir === null) {
            $buffer->addLog('error', __('Path is outside WordPress root or could not be resolved.', 'plugitify'), wp_json_encode($result['debug']));
            $buffer->save();
            return Response::error(
                __('Path is outside WordPress root or could not be resolved.', 'plugitify'),
                $result['debug']
            );
        }
        if (!is_dir($dir)) {
            $buffer->addLog('error', __('Path is not a directory or does not exist.', 'plugitify'), wp_json_encode(array_merge($result['debug'], ['resolved' => $dir])));
            $buffer->save();
            return Response::error(
                __('Path is not a directory or does not exist.', 'plugitify'),
                array_merge($result['debug'], ['resolved' => $dir])
            );
        }
        $caseSensitive = $request->boolean('case_sensitive', false);
        $useRegex      = $request->boolean('regex', false);
        $matches       = $this->grepInDir($dir, $pattern, $caseSensitive, $useRegex);
        $buffer->addLog('info', __('Search completed.', 'plugitify'), wp_json_encode(['match_count' => count($matches)]));
        $buffer->save();
        return Response::success(__('Search completed.', 'plugitify'), [
            'matches' => $matches,
        ]);
    }

    /**
     * Recursively grep pattern in directory.
     *
     * @param string $dir
     * @param string $pattern
     * @param bool   $caseSensitive
     * @param bool   $useRegex
     * @return array<int, array{path: string, line_number: int, line: string}>
     */
    private function grepInDir(string $dir, string $pattern, bool $caseSensitive, bool $useRegex = false): array
    {
        $results = [];
        $flags = $caseSensitive ? '' : 'i';
        $regex = $useRegex ? '#' . $pattern . '#u' . $flags : '#' . preg_quote($pattern, '#') . '#u' . $flags;
        try {
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS | \RecursiveDirectoryIterator::FOLLOW_SYMLINKS),
                \RecursiveIteratorIterator::SELF_FIRST
            );
        } catch (\Throwable $e) {
            return [];
        }
        foreach ($it as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $path = $file->getPathname();
            if (!is_readable($path)) {
                continue;
            }
            $ext = strtolower($file->getExtension());
            $lineNum = 0;
            $handle = fopen($path, 'r');
            if ($handle === false) {
                continue;
            }
            while (($line = fgets($handle)) !== false) {
                $lineNum++;
                if (preg_match($regex, $line)) {
                    $results[] = [
                        'path'        => $path,
                        'line_number' => $lineNum,
                        'line'        => rtrim($line, "\r\n"),
                    ];
                }
            }
            fclose($handle);
        }
        return $results;
    }

    /**
     * List directory: return directories and files in the given path.
     * Input: path.
     *
     * @param Request $request
     * @return array{success: bool, message: string, data: mixed}
     */
    public function listDirectory(Request $request): array
    {
        $path   = $request->str('path', '');
        $buffer = $this->recordFileApi($request, 'file/list-directory', __('List directory', 'plugitify'), ['path' => $path]);

        if ($path === '') {
            $buffer->addLog('error', __('path is required.', 'plugitify'));
            $buffer->save();
            return Response::error(__('path is required.', 'plugitify'));
        }
        $result = $this->resolvePath($path);
        $dir    = $result['resolved'];
        if ($dir === null) {
            $buffer->addLog('error', __('Path is outside WordPress root or could not be resolved.', 'plugitify'), wp_json_encode($result['debug']));
            $buffer->save();
            return Response::error(
                __('Path is outside WordPress root or could not be resolved.', 'plugitify'),
                $result['debug']
            );
        }
        if (!is_dir($dir)) {
            $buffer->addLog('error', __('Path is not a directory or does not exist.', 'plugitify'), wp_json_encode(array_merge($result['debug'], ['resolved' => $dir])));
            $buffer->save();
            return Response::error(
                __('Path is not a directory or does not exist.', 'plugitify'),
                array_merge($result['debug'], ['resolved' => $dir])
            );
        }
        $files       = [];
        $directories = [];
        try {
            $items = new \DirectoryIterator($dir);
        } catch (\Throwable $e) {
            $buffer->addLog('error', __('Cannot read directory.', 'plugitify'), $e->getMessage());
            $buffer->save();
            return Response::error(__('Cannot read directory.', 'plugitify'));
        }
        foreach ($items as $item) {
            if ($item->isDot()) {
                continue;
            }
            $name = $item->getFilename();
            if ($item->isDir()) {
                $directories[] = $name;
            } else {
                $files[] = $name;
            }
        }
        sort($directories);
        sort($files);
        $buffer->addLog('info', __('Directory listed.', 'plugitify'), wp_json_encode(['path' => $dir]));
        $buffer->save();
        return Response::success(__('Directory listed.', 'plugitify'), [
            'path'         => $dir,
            'directories'  => $directories,
            'files'        => $files,
        ]);
    }

    /**
     * Get WordPress root path (ABSPATH).
     *
     * @param Request $request
     * @return array{success: bool, message: string, data: mixed}
     */
    public function wpPath(Request $request): array
    {
        $buffer = $this->recordFileApi($request, 'file/wp-path', __('Get WordPress path', 'plugitify'));
        $path   = rtrim(ABSPATH, DIRECTORY_SEPARATOR);
        $buffer->addLog('info', __('WordPress path returned.', 'plugitify'), wp_json_encode(['path' => $path]));
        $buffer->save();
        return Response::success('', [
            'path' => $path,
        ]);
    }

    /**
     * Read file contents.
     * Input: path.
     *
     * @param Request $request
     * @return array{success: bool, message: string, data: mixed}
     */
    public function readFile(Request $request): array
    {
        $path   = $request->str('path', '');
        $buffer = $this->recordFileApi($request, 'file/read', __('Read file', 'plugitify'), ['path' => $path]);

        if ($path === '') {
            $buffer->addLog('error', __('path is required.', 'plugitify'));
            $buffer->save();
            return Response::error(__('path is required.', 'plugitify'));
        }
        $result   = $this->resolvePath($path);
        $resolved = $result['resolved'];
        if ($resolved === null) {
            $buffer->addLog('error', __('Path is outside WordPress root or could not be resolved.', 'plugitify'), wp_json_encode($result['debug']));
            $buffer->save();
            return Response::error(
                __('Path is outside WordPress root or could not be resolved.', 'plugitify'),
                $result['debug']
            );
        }
        if (!is_file($resolved)) {
            $buffer->addLog('error', __('Path is not a file or does not exist.', 'plugitify'), wp_json_encode(array_merge($result['debug'], ['resolved' => $resolved])));
            $buffer->save();
            return Response::error(
                __('Path is not a file or does not exist.', 'plugitify'),
                array_merge($result['debug'], ['resolved' => $resolved])
            );
        }
        $content = file_get_contents($resolved);
        if ($content === false) {
            $buffer->addLog('error', __('Could not read file.', 'plugitify'), wp_json_encode(['resolved' => $resolved]));
            $buffer->save();
            return Response::error(__('Could not read file.', 'plugitify'), ['resolved' => $resolved]);
        }
        $buffer->addLog('info', __('File read.', 'plugitify'), wp_json_encode(['path' => $resolved]));
        $buffer->save();
        return Response::success(__('File read.', 'plugitify'), [
            'path'    => $resolved,
            'content' => $content,
        ]);
    }

    /**
     * Create empty file. Input: path (file path). No content.
     *
     * @param Request $request
     * @return array{success: bool, message: string, data: mixed}
     */
    public function createFile(Request $request): array
    {
        $path   = $request->str('path', '');
        $buffer = $this->recordFileApi($request, 'file/create', __('Create file', 'plugitify'), ['path' => $path]);

        if ($path === '') {
            $buffer->addLog('error', __('path is required.', 'plugitify'));
            $buffer->save();
            return Response::error(__('path is required.', 'plugitify'));
        }
        $result   = $this->resolvePath($path);
        $resolved = $result['resolved'];
        if ($resolved === null) {
            $buffer->addLog('error', __('Path is outside WordPress root or could not be resolved.', 'plugitify'), wp_json_encode($result['debug']));
            $buffer->save();
            return Response::error(
                __('Path is outside WordPress root or could not be resolved.', 'plugitify'),
                $result['debug']
            );
        }
        $dir = dirname($resolved);
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            $buffer->addLog('error', __('Could not create parent directory.', 'plugitify'), wp_json_encode(['resolved' => $resolved]));
            $buffer->save();
            return Response::error(__('Could not create parent directory.', 'plugitify'));
        }
        if (file_exists($resolved)) {
            $buffer->addLog('error', __('File already exists.', 'plugitify'), wp_json_encode(['resolved' => $resolved]));
            $buffer->save();
            return Response::error(__('File already exists.', 'plugitify'));
        }
        if (file_put_contents($resolved, '') === false) {
            $buffer->addLog('error', __('Could not create file.', 'plugitify'), wp_json_encode(['resolved' => $resolved]));
            $buffer->save();
            return Response::error(__('Could not create file.', 'plugitify'));
        }
        $buffer->addChange('file_created', null, $resolved, wp_json_encode(['path' => $resolved]));
        $buffer->addLog('info', __('File created.', 'plugitify'), wp_json_encode(['path' => $resolved]));
        $buffer->save();
        return Response::success(__('File created.', 'plugitify'));
    }

    /**
     * Create folder. Input: path.
     *
     * @param Request $request
     * @return array{success: bool, message: string, data: mixed}
     */
    public function createFolder(Request $request): array
    {
        $path   = $request->str('path', '');
        $buffer = $this->recordFileApi($request, 'file/create-folder', __('Create folder', 'plugitify'), ['path' => $path]);

        if ($path === '') {
            $buffer->addLog('error', __('path is required.', 'plugitify'));
            $buffer->save();
            return Response::error(__('path is required.', 'plugitify'));
        }
        $result   = $this->resolvePath($path);
        $resolved = $result['resolved'];
        if ($resolved === null) {
            $buffer->addLog('error', __('Path is outside WordPress root or could not be resolved.', 'plugitify'), wp_json_encode($result['debug']));
            $buffer->save();
            return Response::error(
                __('Path is outside WordPress root or could not be resolved.', 'plugitify'),
                $result['debug']
            );
        }
        if (is_dir($resolved)) {
            $buffer->addLog('error', __('Directory already exists.', 'plugitify'), wp_json_encode(['resolved' => $resolved]));
            $buffer->save();
            return Response::error(__('Directory already exists.', 'plugitify'));
        }
        if (!mkdir($resolved, 0755, true)) {
            $buffer->addLog('error', __('Could not create directory.', 'plugitify'), wp_json_encode(['resolved' => $resolved]));
            $buffer->save();
            return Response::error(__('Could not create directory.', 'plugitify'));
        }
        $buffer->addChange('folder_created', null, $resolved, wp_json_encode(['path' => $resolved]));
        $buffer->addLog('info', __('Folder created.', 'plugitify'), wp_json_encode(['path' => $resolved]));
        $buffer->save();
        return Response::success(__('Folder created.', 'plugitify'));
    }

    /**
     * Delete file or directory. Input: path. Directory is removed recursively.
     *
     * @param Request $request
     * @return array{success: bool, message: string, data: mixed}
     */
    public function delete(Request $request): array
    {
        $path   = $request->str('path', '');
        $buffer = $this->recordFileApi($request, 'file/delete', __('Delete file or folder', 'plugitify'), ['path' => $path]);

        if ($path === '') {
            $buffer->addLog('error', __('path is required.', 'plugitify'));
            $buffer->save();
            return Response::error(__('path is required.', 'plugitify'));
        }
        $result   = $this->resolvePath($path);
        $resolved = $result['resolved'];
        if ($resolved === null) {
            $buffer->addLog('error', __('Path is outside WordPress root or could not be resolved.', 'plugitify'), wp_json_encode($result['debug']));
            $buffer->save();
            return Response::error(
                __('Path is outside WordPress root or could not be resolved.', 'plugitify'),
                $result['debug']
            );
        }
        $baseReal = rtrim(realpath(ABSPATH) ?: ABSPATH, '/\\');
        if ($resolved === $baseReal || !$this->pathIsInside($resolved, $baseReal)) {
            $buffer->addLog('error', __('Cannot delete WordPress root.', 'plugitify'), wp_json_encode($result['debug']));
            $buffer->save();
            return Response::error(__('Cannot delete WordPress root.', 'plugitify'), $result['debug']);
        }
        if (!file_exists($resolved)) {
            $buffer->addLog('error', __('Path does not exist.', 'plugitify'), wp_json_encode(array_merge($result['debug'], ['resolved' => $resolved])));
            $buffer->save();
            return Response::error(
                __('Path does not exist.', 'plugitify'),
                array_merge($result['debug'], ['resolved' => $resolved])
            );
        }
        if (is_file($resolved)) {
            if (!@unlink($resolved)) {
                $buffer->addLog('error', __('Could not delete file.', 'plugitify'), wp_json_encode(['resolved' => $resolved]));
                $buffer->save();
                return Response::error(__('Could not delete file.', 'plugitify'), ['resolved' => $resolved]);
            }
            $buffer->addChange('file_deleted', $resolved, null, wp_json_encode(['path' => $resolved]));
            $buffer->addLog('info', __('File deleted.', 'plugitify'), wp_json_encode(['path' => $resolved]));
            $buffer->save();
            return Response::success(__('File deleted.', 'plugitify'));
        }
        if (is_dir($resolved)) {
            if (!$this->deleteRecursive($resolved)) {
                $buffer->addLog('error', __('Could not delete directory.', 'plugitify'), wp_json_encode(['resolved' => $resolved]));
                $buffer->save();
                return Response::error(__('Could not delete directory.', 'plugitify'), ['resolved' => $resolved]);
            }
            $buffer->addChange('folder_deleted', $resolved, null, wp_json_encode(['path' => $resolved]));
            $buffer->addLog('info', __('Directory deleted.', 'plugitify'), wp_json_encode(['path' => $resolved]));
            $buffer->save();
            return Response::success(__('Directory deleted.', 'plugitify'));
        }
        $buffer->addLog('error', __('Path is not a file or directory.', 'plugitify'), wp_json_encode(['resolved' => $resolved]));
        $buffer->save();
        return Response::error(__('Path is not a file or directory.', 'plugitify'), ['resolved' => $resolved]);
    }

    /**
     * Delete directory and all its contents recursively.
     *
     * @param string $dir
     * @return bool
     */
    private function deleteRecursive(string $dir): bool
    {
        if (!is_dir($dir)) {
            return false;
        }
        $items = array_diff(scandir($dir), ['.', '..']);
        foreach ($items as $item) {
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                if (!$this->deleteRecursive($path)) {
                    return false;
                }
            } else {
                if (!@unlink($path)) {
                    return false;
                }
            }
        }
        return @rmdir($dir);
    }

    /**
     * Replace entire file content with new text.
     * Input: path, content.
     *
     * @param Request $request
     * @return array{success: bool, message: string, data: mixed}
     */
    public function replaceContent(Request $request): array
    {
        $path   = $request->str('path', '');
        $buffer = $this->recordFileApi($request, 'file/replace-content', __('Replace file content', 'plugitify'), ['path' => $path]);

        if ($path === '') {
            $buffer->addLog('error', __('path is required.', 'plugitify'));
            $buffer->save();
            return Response::error(__('path is required.', 'plugitify'));
        }
        $result   = $this->resolvePath($path);
        $resolved = $result['resolved'];
        if ($resolved === null) {
            $buffer->addLog('error', __('Path is outside WordPress root or could not be resolved.', 'plugitify'), wp_json_encode($result['debug']));
            $buffer->save();
            return Response::error(
                __('Path is outside WordPress root or could not be resolved.', 'plugitify'),
                $result['debug']
            );
        }
        if (!is_file($resolved)) {
            $buffer->addLog('error', __('Path is not a file or does not exist.', 'plugitify'), wp_json_encode(array_merge($result['debug'], ['resolved' => $resolved])));
            $buffer->save();
            return Response::error(
                __('Path is not a file or does not exist.', 'plugitify'),
                array_merge($result['debug'], ['resolved' => $resolved])
            );
        }
        $oldContent = file_get_contents($resolved);
        $content    = $request->input('content');
        if ($content === null) {
            $content = $request->getContent();
        }
        if (is_array($content)) {
            $content = '';
        }
        $content = (string) $content;
        if (file_put_contents($resolved, $content) === false) {
            $buffer->addLog('error', __('Could not write file.', 'plugitify'), wp_json_encode(['resolved' => $resolved]));
            $buffer->save();
            return Response::error(__('Could not write file.', 'plugitify'));
        }
        $buffer->addChange('file_content_replaced', $oldContent !== false ? (string) $oldContent : null, $content, wp_json_encode(['path' => $resolved]));
        $buffer->addLog('info', __('File content replaced.', 'plugitify'), wp_json_encode(['path' => $resolved]));
        $buffer->save();
        return Response::success(__('File content replaced.', 'plugitify'));
    }

    /**
     * Replace a specific line in a file.
     * Input: path, line_number (1-based), content (new line content).
     *
     * @param Request $request
     * @return array{success: bool, message: string, data: mixed}
     */
    public function replaceLine(Request $request): array
    {
        $path       = $request->str('path', '');
        $lineNumber = $request->integer('line_number', 0);
        $newContent = $request->str('content', '');
        $buffer     = $this->recordFileApi($request, 'file/replace-line', __('Replace line in file', 'plugitify'), [
            'path'        => $path,
            'line_number' => $lineNumber,
        ]);

        if ($path === '' || $lineNumber < 1) {
            $buffer->addLog('error', __('path and line_number (>= 1) are required.', 'plugitify'));
            $buffer->save();
            return Response::error(__('path and line_number (>= 1) are required.', 'plugitify'));
        }
        $result   = $this->resolvePath($path);
        $resolved = $result['resolved'];
        if ($resolved === null) {
            $buffer->addLog('error', __('Path is outside WordPress root or could not be resolved.', 'plugitify'), wp_json_encode($result['debug']));
            $buffer->save();
            return Response::error(
                __('Path is outside WordPress root or could not be resolved.', 'plugitify'),
                $result['debug']
            );
        }
        if (!is_file($resolved)) {
            $buffer->addLog('error', __('Path is not a file or does not exist.', 'plugitify'), wp_json_encode(array_merge($result['debug'], ['resolved' => $resolved])));
            $buffer->save();
            return Response::error(
                __('Path is not a file or does not exist.', 'plugitify'),
                array_merge($result['debug'], ['resolved' => $resolved])
            );
        }
        $lines = file($resolved, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            $buffer->addLog('error', __('Could not read file.', 'plugitify'), wp_json_encode(['resolved' => $resolved]));
            $buffer->save();
            return Response::error(__('Could not read file.', 'plugitify'));
        }
        if ($lineNumber > count($lines)) {
            $buffer->addLog('error', __('Line number out of range.', 'plugitify'), wp_json_encode(['resolved' => $resolved, 'line_number' => $lineNumber]));
            $buffer->save();
            return Response::error(__('Line number out of range.', 'plugitify'));
        }
        $oldLine = $lines[$lineNumber - 1];
        $lines[$lineNumber - 1] = $newContent;
        $content = implode("\n", $lines);
        if (file_put_contents($resolved, $content) === false) {
            $buffer->addLog('error', __('Could not write file.', 'plugitify'), wp_json_encode(['resolved' => $resolved]));
            $buffer->save();
            return Response::error(__('Could not write file.', 'plugitify'));
        }
        $buffer->addChange('file_line_replaced', $oldLine, $newContent, wp_json_encode(['path' => $resolved, 'line_number' => $lineNumber]));
        $buffer->addLog('info', __('Line replaced.', 'plugitify'), wp_json_encode(['path' => $resolved, 'line_number' => $lineNumber]));
        $buffer->save();
        return Response::success(__('Line replaced.', 'plugitify'));
    }
}
