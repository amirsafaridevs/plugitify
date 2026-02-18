<?php

namespace Plugifity\Service\Tools;

if (!defined('ABSPATH')) {
    exit;
}

use Plugifity\Contract\Abstract\AbstractService;
use Plugifity\Core\DB;
use Plugifity\Core\Http\ApiRouter;
use Plugifity\Core\Http\Request;
use Plugifity\Core\Http\Response;
use Plugifity\Core\ToolsPolicy;
use Plugifity\Helper\RecordBuffer;

/**
 * API Tools – Query service (read, execute, create table, backup, restore).
 */
class Query extends AbstractService
{
    private const API_SOURCE = 'api.query';
     /**
     * Boot the service – register query API routes.
     *
     * @return void
     */
    public function boot(): void
    {
        ApiRouter::post('query/read', [$this, 'read'])->name('api.tools.query.read')->tool('query', 'read');
        ApiRouter::post('query/execute', [$this, 'execute'])->name('api.tools.query.execute')->tool('query', 'execute');
        ApiRouter::post('query/create-table', [$this, 'createTable'])->name('api.tools.query.create-table')->tool('query', 'create-table');
        ApiRouter::post('query/backup', [$this, 'backup'])->name('api.tools.query.backup')->tool('query', 'backup');
        ApiRouter::post('query/backup-list', [$this, 'listBackups'])->name('api.tools.query.backup-list')->tool('query', 'backup-list');
        ApiRouter::post('query/restore', [$this, 'restore'])->name('api.tools.query.restore')->tool('query', 'restore');
        ApiRouter::post('query/tables', [$this, 'listTables'])->name('api.tools.query.tables')->tool('query', 'tables');
    }
    /**
     * Record query API call and return buffer.
     *
     * @param Request $request
     * @param string  $endpoint
     * @param string  $title
     * @param array   $details
     * @return RecordBuffer
     */
    private function recordQueryApi(Request $request, string $endpoint, string $title, array $details = []): RecordBuffer
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
     * Get full table name and ensure it belongs to WordPress (prefix).
     *
     * @param string $table
     * @return string
     */
    private function getTableName(string $table): string
    {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        if ($table === '') {
            return '';
        }
        $prefix = DB::getPrefix();
        if ($prefix !== '' && strpos($table, $prefix) !== 0) {
            return $prefix . $table;
        }
        return $table;
    }

    /**
     * Check if SQL is read-only (SELECT only).
     *
     * @param string $sql
     * @return bool
     */
    private function isSelectOnly(string $sql): bool
    {
        $sql = preg_replace('/--.*$/m', '', $sql);
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
        $sql = trim($sql);
        return str_starts_with(strtoupper($sql), 'SELECT');
    }

    /**
     * Check if SQL is a write statement (INSERT/UPDATE/DELETE).
     *
     * @param string $sql
     * @return bool
     */
    private function isWriteStatement(string $sql): bool
    {
        $sql = preg_replace('/--.*$/m', '', $sql);
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
        $sql = trim($sql);
        $upper = strtoupper($sql);
        return str_starts_with($upper, 'INSERT') || str_starts_with($upper, 'UPDATE') || str_starts_with($upper, 'DELETE');
    }

    /**
     * Check if SQL is CREATE TABLE only.
     *
     * @param string $sql
     * @return bool
     */
    private function isCreateTableOnly(string $sql): bool
    {
        $sql = preg_replace('/--.*$/m', '', $sql);
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
        $sql = trim($sql);
        return str_starts_with(strtoupper($sql), 'CREATE TABLE');
    }

    /**
     * Backup directory inside wp-content (no trailing slash).
     *
     * @return string
     */
    private function getBackupDir(): string
    {
        $dir = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'plugitify-backups';
        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
        }
        return $dir;
    }

    /**
     * Save backup payload to a file in wp-content/plugitify-backups.
     * Filename: backup-{table}-{Y-m-d}-{n}.json
     *
     * @param string $fullTable
     * @param string $createSql
     * @param array  $rows
     * @return string|null Full path of saved file or null on failure
     */
    private function saveBackupToFile(string $fullTable, string $createSql, array $rows): ?string
    {
        $dir = $this->getBackupDir();
        if (!is_dir($dir) || !is_writable($dir)) {
            return null;
        }

        $date     = gmdate('Y-m-d');
        $tableSafe = preg_replace('/[^a-zA-Z0-9_]/', '', $fullTable);
        $prefix   = 'backup-' . $tableSafe . '-' . $date . '-';

        $existing = glob($dir . DIRECTORY_SEPARATOR . $prefix . '*.json');
        $existing = is_array($existing) ? $existing : [];
        $num      = count($existing) + 1;
        $filename = $prefix . $num . '.json';
        $path     = $dir . DIRECTORY_SEPARATOR . $filename;

        $payload = [
            'table'      => $fullTable,
            'create_sql' => $createSql,
            'rows'       => $rows,
            'row_count'  => count($rows),
            'backup_at'  => gmdate('Y-m-d H:i:s'),
        ];

        $json = wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($json === false || file_put_contents($path, $json) === false) {
            return null;
        }

        return $path;
    }

    /**
     * Resolve backup file path: must be inside plugitify-backups dir. Returns real path or null.
     *
     * @param string $pathOrFilename Full path or just filename (e.g. backup-wp_options-2026-02-12-1.json)
     * @return string|null
     */
    private function resolveBackupFilePath(string $pathOrFilename): ?string
    {
        $pathOrFilename = trim($pathOrFilename);
        if ($pathOrFilename === '') {
            return null;
        }
        $dir = $this->getBackupDir();
        if (strpos($pathOrFilename, $dir) === 0) {
            $resolved = realpath($pathOrFilename);
        } else {
            $basename = basename($pathOrFilename);
            if (preg_match('/^backup-[a-zA-Z0-9_]+-\d{4}-\d{2}-\d{2}-\d+\.json$/', $basename) !== 1) {
                return null;
            }
            $resolved = realpath($dir . DIRECTORY_SEPARATOR . $basename);
        }
        if ($resolved === false || !is_file($resolved) || !is_readable($resolved)) {
            return null;
        }
        $dirReal = realpath($dir);
        if ($dirReal === false || strpos($resolved, $dirReal) !== 0) {
            return null;
        }
        return $resolved;
    }

  

    /**
     * Run a read-only SELECT query.
     * Body: sql (string), bindings (array, optional).
     *
     * @param Request $request
     * @return array{success: bool, message: string, data: mixed}
     */
    public function read(Request $request): array
    {
        if (($r = ToolsPolicy::getDisabledResponse('query', 'read')) !== null) {
            return $r;
        }
        $sql      = $request->str('sql', '');
        $bindings = $request->input('bindings', []);
        $bindings = is_array($bindings) ? $bindings : [];
        $buffer   = $this->recordQueryApi($request, 'query/read', __('Run read query', 'plugitify'), ['sql' => $sql]);

        if ($sql === '') {
            $buffer->addLog('error', __('sql is required.', 'plugitify'));
            $buffer->save();
            return Response::error(__('sql is required.', 'plugitify'));
        }

        $sql = preg_replace('/\s*;\s*$/', '', trim($sql));
        if (!$this->isSelectOnly($sql)) {
            $buffer->addLog('error', __('Only SELECT queries are allowed for read.', 'plugitify'));
            $buffer->save();
            return Response::error(__('Only SELECT queries are allowed for read.', 'plugitify'));
        }

        try {
            $results = DB::select($sql, array_values($bindings));
            $buffer->addLog('info', __('Query executed.', 'plugitify'), wp_json_encode(['row_count' => count($results)]));
            $buffer->save();
            return Response::success(__('Query executed.', 'plugitify'), [
                'rows' => $results,
                'count' => count($results),
            ]);
        } catch (\Throwable $e) {
            $buffer->addLog('error', $e->getMessage(), wp_json_encode(['sql' => $sql]));
            $buffer->save();
            return Response::error(__('Query failed.', 'plugitify'), ['message' => $e->getMessage()]);
        }
    }

    /**
     * Run a write query (INSERT, UPDATE, DELETE).
     * Body: sql (string), bindings (array, optional).
     *
     * @param Request $request
     * @return array{success: bool, message: string, data: mixed}
     */
    public function execute(Request $request): array
    {
        if (($r = ToolsPolicy::getDisabledResponse('query', 'execute')) !== null) {
            return $r;
        }
        $sql      = $request->str('sql', '');
        $bindings = $request->input('bindings', []);
        $bindings = is_array($bindings) ? $bindings : [];
        $buffer   = $this->recordQueryApi($request, 'query/execute', __('Run write query', 'plugitify'), ['sql' => $sql]);

        if ($sql === '') {
            $buffer->addLog('error', __('sql is required.', 'plugitify'));
            $buffer->save();
            return Response::error(__('sql is required.', 'plugitify'));
        }

        $sql = preg_replace('/\s*;\s*$/', '', trim($sql));
        if (!$this->isWriteStatement($sql)) {
            $buffer->addLog('error', __('Only INSERT, UPDATE, or DELETE are allowed for execute.', 'plugitify'));
            $buffer->save();
            return Response::error(__('Only INSERT, UPDATE, or DELETE are allowed for execute.', 'plugitify'));
        }

        try {
            $wpdb    = DB::connection();
            $prepared = $bindings !== [] ? $wpdb->prepare($sql, ...array_values($bindings)) : $sql;
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared above or validated
            $affected = $wpdb->query($prepared);
            if ($affected === false) {
                $buffer->addLog('error', DB::lastError(), wp_json_encode(['sql' => $sql]));
                $buffer->save();
                return Response::error(__('Query failed.', 'plugitify'), ['message' => DB::lastError()]);
            }
            $buffer->addChange('query_executed', null, (string) $affected, wp_json_encode(['sql' => $sql]));
            $buffer->addLog('info', __('Query executed.', 'plugitify'), wp_json_encode(['affected' => $affected]));
            $buffer->save();
            return Response::success(__('Query executed.', 'plugitify'), [
                'affected_rows' => $affected,
                'insert_id'     => $wpdb->insert_id ? (int) $wpdb->insert_id : null,
            ]);
        } catch (\Throwable $e) {
            $buffer->addLog('error', $e->getMessage(), wp_json_encode(['sql' => $sql]));
            $buffer->save();
            return Response::error(__('Query failed.', 'plugitify'), ['message' => $e->getMessage()]);
        }
    }

    /**
     * Create a new table.
     * Body: table (string), columns (array of { name, type, length?, nullable?, primary? }) OR sql (raw CREATE TABLE string).
     *
     * @param Request $request
     * @return array{success: bool, message: string, data: mixed}
     */
    public function createTable(Request $request): array
    {
        if (($r = ToolsPolicy::getDisabledResponse('query', 'create-table')) !== null) {
            return $r;
        }
        $table   = $request->str('table', '');
        $columns = $request->input('columns', []);
        $columns = is_array($columns) ? $columns : [];
        $rawSql  = $request->str('sql', '');
        $buffer  = $this->recordQueryApi($request, 'query/create-table', __('Create table', 'plugitify'), [
            'table' => $table,
            'has_columns' => count($columns) > 0,
            'has_sql' => $rawSql !== '',
        ]);

        if ($rawSql !== '') {
            $rawSql = preg_replace('/\s*;\s*$/', '', trim($rawSql));
            if (!$this->isCreateTableOnly($rawSql)) {
                $buffer->addLog('error', __('Only CREATE TABLE statements are allowed.', 'plugitify'));
                $buffer->save();
                return Response::error(__('Only CREATE TABLE statements are allowed.', 'plugitify'));
            }
            try {
                DB::statement($rawSql);
                $buffer->addChange('table_created', null, 'raw', wp_json_encode(['sql' => $rawSql]));
                $buffer->addLog('info', __('Table created.', 'plugitify'));
                $buffer->save();
                return Response::success(__('Table created.', 'plugitify'));
            } catch (\Throwable $e) {
                $buffer->addLog('error', $e->getMessage(), wp_json_encode(['sql' => $rawSql]));
                $buffer->save();
                return Response::error(__('Create table failed.', 'plugitify'), ['message' => $e->getMessage()]);
            }
        }

        if ($table === '' || empty($columns)) {
            $buffer->addLog('error', __('table and columns are required when not using sql.', 'plugitify'));
            $buffer->save();
            return Response::error(__('table and columns are required when not using sql.', 'plugitify'));
        }

        $fullTable = $this->getTableName($table);
        if ($fullTable === '') {
            $buffer->addLog('error', __('Invalid table name.', 'plugitify'));
            $buffer->save();
            return Response::error(__('Invalid table name.', 'plugitify'));
        }

        try {
            $schema = DB::schema();
            $schema->create($fullTable, function ($blueprint) use ($columns) {
                foreach ($columns as $col) {
                    $name     = isset($col['name']) ? (string) $col['name'] : '';
                    $type     = isset($col['type']) ? strtolower((string) $col['type']) : 'string';
                    $length   = isset($col['length']) ? (int) $col['length'] : null;
                    $nullable = !empty($col['nullable']);

                    if ($name === '') {
                        continue;
                    }

                    switch ($type) {
                        case 'id':
                            $blueprint->id($name);
                            break;
                        case 'int':
                        case 'integer':
                            $blueprint->integer($name, $length ?: 11);
                            break;
                        case 'bigint':
                            $blueprint->bigInteger($name);
                            break;
                        case 'string':
                        case 'varchar':
                            $blueprint->string($name, $length ?: 255);
                            break;
                        case 'text':
                            $blueprint->text($name);
                            break;
                        case 'longtext':
                            $blueprint->longText($name);
                            break;
                        case 'boolean':
                            $blueprint->boolean($name);
                            break;
                        case 'datetime':
                        case 'timestamp':
                            $blueprint->dateTime($name);
                            break;
                        case 'date':
                            $blueprint->date($name);
                            break;
                        case 'decimal':
                            $total  = isset($col['total']) ? (int) $col['total'] : 8;
                            $places = isset($col['places']) ? (int) $col['places'] : 2;
                            $blueprint->decimal($name, $total, $places);
                            break;
                        default:
                            $blueprint->string($name, $length ?: 255);
                    }
                    if ($nullable && $type !== 'id') {
                        $blueprint->nullable($name);
                    }
                }
            });
            $buffer->addChange('table_created', null, $fullTable, wp_json_encode(['table' => $fullTable]));
            $buffer->addLog('info', __('Table created.', 'plugitify'), wp_json_encode(['table' => $fullTable]));
            $buffer->save();
            return Response::success(__('Table created.', 'plugitify'), ['table' => $fullTable]);
        } catch (\Throwable $e) {
            $buffer->addLog('error', $e->getMessage(), wp_json_encode(['table' => $fullTable]));
            $buffer->save();
            return Response::error(__('Create table failed.', 'plugitify'), ['message' => $e->getMessage()]);
        }
    }

    /**
     * Backup a table: structure (SHOW CREATE TABLE) + all rows.
     * Body: table (string).
     *
     * @param Request $request
     * @return array{success: bool, message: string, data: mixed}
     */
    public function backup(Request $request): array
    {
        if (($r = ToolsPolicy::getDisabledResponse('query', 'backup')) !== null) {
            return $r;
        }
        $table  = $request->str('table', '');
        $buffer = $this->recordQueryApi($request, 'query/backup', __('Backup table', 'plugitify'), ['table' => $table]);

        if ($table === '') {
            $buffer->addLog('error', __('table is required.', 'plugitify'));
            $buffer->save();
            return Response::error(__('table is required.', 'plugitify'));
        }

        $fullTable = $this->getTableName($table);
        if ($fullTable === '') {
            $buffer->addLog('error', __('Invalid table name.', 'plugitify'));
            $buffer->save();
            return Response::error(__('Invalid table name.', 'plugitify'));
        }

        $schema = DB::schema();
        if (!$schema->hasTable($fullTable)) {
            $buffer->addLog('error', __('Table does not exist.', 'plugitify'), wp_json_encode(['table' => $fullTable]));
            $buffer->save();
            return Response::error(__('Table does not exist.', 'plugitify'), ['table' => $fullTable]);
        }

        try {
            $wpdb = DB::connection();
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table name is validated (prefixed)
            $createRow = $wpdb->get_row('SHOW CREATE TABLE `' . esc_sql($fullTable) . '`', ARRAY_N);
            $createSql = $createRow && isset($createRow[1]) ? $createRow[1] : '';

            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table name escaped
            $rows = $wpdb->get_results('SELECT * FROM `' . esc_sql($fullTable) . '`', OBJECT);
            $rows = is_array($rows) ? $rows : [];

            $savedPath = $this->saveBackupToFile($fullTable, $createSql, $rows);
            if ($savedPath !== null) {
                $buffer->addLog('info', __('Backup saved to file.', 'plugitify'), wp_json_encode(['path' => $savedPath]));
            }

            $buffer->addLog('info', __('Table backed up.', 'plugitify'), wp_json_encode(['table' => $fullTable, 'row_count' => count($rows)]));
            $buffer->save();

            $data = [
                'table'     => $fullTable,
                'row_count' => count($rows),
            ];
            if ($savedPath !== null) {
                $data['saved_to'] = $savedPath;
            }

            return Response::success(__('Table backed up.', 'plugitify'), $data);
        } catch (\Throwable $e) {
            $buffer->addLog('error', $e->getMessage(), wp_json_encode(['table' => $fullTable]));
            $buffer->save();
            return Response::error(__('Backup failed.', 'plugitify'), ['message' => $e->getMessage()]);
        }
    }

    /**
     * List backup files in wp-content/plugitify-backups with path and metadata.
     * Body: optional ({}).
     *
     * @param Request $request
     * @return array{success: bool, message: string, data: mixed}
     */
    public function listBackups(Request $request): array
    {
        if (($r = ToolsPolicy::getDisabledResponse('query', 'backup-list')) !== null) {
            return $r;
        }
        $buffer = $this->recordQueryApi($request, 'query/backup-list', __('List backups', 'plugitify'));

        try {
            $dir = $this->getBackupDir();
            if (!is_dir($dir) || !is_readable($dir)) {
                $buffer->addLog('info', __('Backup directory not found or not readable.', 'plugitify'));
                $buffer->save();
                return Response::success(__('Backups listed.', 'plugitify'), ['backups' => [], 'count' => 0]);
            }

            $files = glob($dir . DIRECTORY_SEPARATOR . 'backup-*.json');
            $files = is_array($files) ? $files : [];
            rsort($files, SORT_NATURAL);

            $list = [];
            foreach ($files as $path) {
                $path = (string) $path;
                $filename = basename($path);
                $item = [
                    'path'     => $path,
                    'filename' => $filename,
                ];
                $content = @file_get_contents($path);
                if ($content !== false) {
                    $data = json_decode($content, true);
                    if (is_array($data)) {
                        $item['table']      = isset($data['table']) ? (string) $data['table'] : null;
                        $item['row_count']  = isset($data['row_count']) ? (int) $data['row_count'] : null;
                        $item['backup_at']  = isset($data['backup_at']) ? (string) $data['backup_at'] : null;
                    }
                }
                $list[] = $item;
            }

            $buffer->addLog('info', __('Backups listed.', 'plugitify'), wp_json_encode(['count' => count($list)]));
            $buffer->save();
            return Response::success(__('Backups listed.', 'plugitify'), [
                'backups' => $list,
                'count'   => count($list),
            ]);
        } catch (\Throwable $e) {
            $buffer->addLog('error', $e->getMessage());
            $buffer->save();
            return Response::error(__('List backups failed.', 'plugitify'), ['message' => $e->getMessage()]);
        }
    }

    /**
     * Restore a table from backup payload or from backup file path.
     * Body: file (path to backup file) OR backup (object) OR create_sql + rows; optional drop_first (bool, default true).
     *
     * @param Request $request
     * @return array{success: bool, message: string, data: mixed}
     */
    public function restore(Request $request): array
    {
        if (($r = ToolsPolicy::getDisabledResponse('query', 'restore')) !== null) {
            return $r;
        }
        $backup    = $request->input('backup');
        $table     = $request->str('table', '');
        $createSql = $request->str('create_sql', '');
        $rows      = $request->input('rows', []);
        $rows      = is_array($rows) ? $rows : [];
        $dropFirst = $request->boolean('drop_first', true);

        $backupFile = $request->str('file', '');
        if ($backupFile !== '') {
            $resolved = $this->resolveBackupFilePath($backupFile);
            if ($resolved === null) {
                $buffer = $this->recordQueryApi($request, 'query/restore', __('Restore table', 'plugitify'), ['file' => $backupFile]);
                $buffer->addLog('error', __('Backup file not found or not allowed.', 'plugitify'), wp_json_encode(['file' => $backupFile]));
                $buffer->save();
                return Response::error(__('Backup file not found or not allowed.', 'plugitify'), ['file' => $backupFile]);
            }
            $content = file_get_contents($resolved);
            $backup  = $content !== false ? json_decode($content, true) : null;
            if (!is_array($backup)) {
                $buffer = $this->recordQueryApi($request, 'query/restore', __('Restore table', 'plugitify'), ['file' => $backupFile]);
                $buffer->addLog('error', __('Invalid backup file content.', 'plugitify'));
                $buffer->save();
                return Response::error(__('Invalid backup file content.', 'plugitify'));
            }
        }

        if (is_array($backup)) {
            $table     = $table !== '' ? $table : (isset($backup['table']) ? (string) $backup['table'] : '');
            $createSql = $createSql !== '' ? $createSql : (isset($backup['create_sql']) ? (string) $backup['create_sql'] : '');
            $rows      = !empty($rows) ? $rows : (isset($backup['rows']) && is_array($backup['rows']) ? $backup['rows'] : []);
        }

        $buffer = $this->recordQueryApi($request, 'query/restore', __('Restore table', 'plugitify'), [
            'table' => $table,
            'row_count' => count($rows),
        ]);

        if ($createSql === '') {
            $buffer->addLog('error', __('create_sql or backup.create_sql is required.', 'plugitify'));
            $buffer->save();
            return Response::error(__('create_sql or backup.create_sql is required.', 'plugitify'));
        }

        $createSql = trim($createSql);
        if (!$this->isCreateTableOnly($createSql)) {
            $buffer->addLog('error', __('create_sql must be a CREATE TABLE statement.', 'plugitify'));
            $buffer->save();
            return Response::error(__('create_sql must be a CREATE TABLE statement.', 'plugitify'));
        }

        try {
            $wpdb = DB::connection();

            $fullTableFromCreate = null;
            if (preg_match('/CREATE\s+TABLE\s+[`"]?([a-zA-Z0-9_]+)[`"]?/i', $createSql, $m)) {
                $fullTableFromCreate = $m[1];
            }

            if ($dropFirst && $fullTableFromCreate !== '') {
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- DDL, table name escaped
                $wpdb->query('DROP TABLE IF EXISTS `' . esc_sql($fullTableFromCreate) . '`');
            }

            $result = DB::statement($createSql);
            if ($result === false) {
                $buffer->addLog('error', DB::lastError(), wp_json_encode(['create_sql' => $createSql]));
                $buffer->save();
                return Response::error(__('Restore failed: could not create table.', 'plugitify'), ['message' => DB::lastError()]);
            }

            $inserted = 0;
            if (!empty($rows)) {
                $first    = reset($rows);
                $rowObj   = is_object($first) ? (array) $first : $first;
                $columns  = array_keys($rowObj);
                $targetTable = $fullTableFromCreate ?: $this->getTableName($table);

                foreach ($rows as $row) {
                    $data = is_object($row) ? (array) $row : $row;
                    $data = array_intersect_key($data, array_flip($columns));
                    if ($data === []) {
                        continue;
                    }
                    $insertResult = $wpdb->insert($targetTable, $data);
                    if ($insertResult !== false) {
                        $inserted++;
                    }
                }
            }

            $buffer->addChange('table_restored', null, $table ?: 'from_backup', wp_json_encode(['row_count' => $inserted]));
            $buffer->addLog('info', __('Table restored.', 'plugitify'), wp_json_encode(['inserted' => $inserted]));
            $buffer->save();
            return Response::success(__('Table restored.', 'plugitify'), [
                'inserted_rows' => $inserted,
            ]);
        } catch (\Throwable $e) {
            $buffer->addLog('error', $e->getMessage());
            $buffer->save();
            return Response::error(__('Restore failed.', 'plugitify'), ['message' => $e->getMessage()]);
        }
    }

    /**
     * List all tables in the current WordPress database with their columns.
     * Body: optional (empty or {}). Optional filter: prefix_only (bool) – if true, only tables with wp prefix.
     *
     * @param Request $request
     * @return array{success: bool, message: string, data: mixed}
     */
    public function listTables(Request $request): array
    {
        if (($r = ToolsPolicy::getDisabledResponse('query', 'tables')) !== null) {
            return $r;
        }
        $prefixOnly = $request->boolean('prefix_only', false);
        $buffer     = $this->recordQueryApi($request, 'query/tables', __('List database tables', 'plugitify'), ['prefix_only' => $prefixOnly]);

        try {
            $wpdb   = DB::connection();
            $schema = defined('DB_NAME') ? DB_NAME : '';

            if ($schema === '') {
                $buffer->addLog('error', __('Database name not defined.', 'plugitify'));
                $buffer->save();
                return Response::error(__('Database name not defined.', 'plugitify'));
            }

            $tablesSql = 'SELECT table_name FROM information_schema.tables WHERE table_schema = %s AND table_type = %s ORDER BY table_name';
            $tables    = $wpdb->get_results($wpdb->prepare($tablesSql, $schema, 'BASE TABLE'), OBJECT_K);

            if (!is_array($tables)) {
                $tables = [];
            }

            $prefix = DB::getPrefix();
            if ($prefix !== '' && $prefixOnly) {
                $tables = array_filter($tables, function ($row, $name) use ($prefix) {
                    return strpos($name, $prefix) === 0;
                }, ARRAY_FILTER_USE_BOTH);
            }

            $result = [];
            $colsSql = 'SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_KEY, COLUMN_DEFAULT, EXTRA 
                FROM information_schema.columns 
                WHERE table_schema = %s AND table_name = %s 
                ORDER BY ORDINAL_POSITION';

            foreach ($tables as $tableName => $unused) {
                $tableName = (string) $tableName;
                $columns   = $wpdb->get_results($wpdb->prepare($colsSql, $schema, $tableName), OBJECT);

                $result[] = [
                    'name'    => $tableName,
                    'columns' => array_map(function ($col) {
                        $name     = $col->COLUMN_NAME ?? $col->column_name ?? '';
                        $type     = $col->DATA_TYPE ?? $col->data_type ?? '';
                        $nullable = $col->IS_NULLABLE ?? $col->is_nullable ?? '';
                        $key      = $col->COLUMN_KEY ?? $col->column_key ?? '';
                        $default  = $col->COLUMN_DEFAULT ?? $col->column_default ?? null;
                        $extra    = $col->EXTRA ?? $col->extra ?? '';
                        return [
                            'name'     => (string) $name,
                            'type'     => (string) $type,
                            'nullable' => (strtoupper((string) $nullable) === 'YES'),
                            'key'      => (string) $key,
                            'default'  => $default,
                            'extra'    => (string) $extra,
                        ];
                    }, $columns !== null ? $columns : []),
                ];
            }

            $buffer->addLog('info', __('Tables listed.', 'plugitify'), wp_json_encode(['table_count' => count($result)]));
            $buffer->save();
            return Response::success(__('Tables listed.', 'plugitify'), [
                'tables' => $result,
                'count'  => count($result),
            ]);
        } catch (\Throwable $e) {
            $buffer->addLog('error', $e->getMessage());
            $buffer->save();
            return Response::error(__('List tables failed.', 'plugitify'), ['message' => $e->getMessage()]);
        }
    }
}
