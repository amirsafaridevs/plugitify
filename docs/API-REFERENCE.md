# Plugitify Backend API & Tools Reference

This document describes all REST API endpoints and tools available for backend/agent use. Use it to configure an AI model or any client that talks to the Plugitify API.

**Base URL (replace with your WordPress site URL):**

```
{SITE_URL}/wp-json/plugitify/v1/api
```

**Examples:**
- `https://example.com/wp-json/plugitify/v1/api`
- `http://localhost/wpagentify/wordpress/wp-json/plugitify/v1/api`

**Conventions:**
- All tool endpoints (Query, File, General) use **POST** with **Content-Type: application/json** and a JSON body, unless noted.
- **Tools API Token:** Every request to a **tool** endpoint (all of Query, File, and General) must include the Tools API Token in a header. See [§1.3 Tools API Token](#13-tools-api-token-authentication-for-tools).
- Success responses: `{ "success": true, "message": "...", "data": { ... } }`
- Error responses: `{ "success": false, "message": "...", "data": { ... } }`
- File/directory paths can be relative to WordPress root (ABSPATH) or absolute; they must stay inside the WordPress root.

---

## Table of Contents

1. [Core API](#1-core-api) (includes [Tools API Token](#13-tools-api-token-authentication-for-tools))
2. [Query Tools (Database)](#2-query-tools-database)
3. [File Tools](#3-file-tools)
4. [General Tools (Plugins, Themes, Debug, URLs, Log)](#4-general-tools)

---

## 1. Core API

### 1.1 Ping (Health Check)

**Purpose:** Check that the API is reachable. No authentication required.

| Item    | Value |
|--------|--------|
| **URL** | `GET {SITE_URL}/wp-json/plugitify/v1/api/ping` |
| **Method** | GET |
| **Body** | None |

**Example request:**
```http
GET /wp-json/plugitify/v1/api/ping
```

**Example response:**
```json
{
  "success": true,
  "message": "Ping successful",
  "data": []
}
```

**Use case:** Health checks, connectivity tests before calling other endpoints.

---

### 1.2 License Check

**Purpose:** Verify that a given license key matches the one stored in plugin settings.

| Item    | Value |
|--------|--------|
| **URL** | `POST {SITE_URL}/wp-json/plugitify/v1/api/license-check` |
| **Method** | POST |
| **Body** | JSON: `license` (string) |

**Example request:**
```json
{
  "license": "your-license-key-here"
}
```

**Example response (valid):**
```json
{
  "success": true,
  "message": "License is correct and matches the one registered in settings.",
  "data": []
}
```

**Example response (invalid):**
```json
{
  "success": false,
  "message": "License does not match the one registered in settings.",
  "data": []
}
```

**Use case:** Validate license before performing sensitive operations.

---

### 1.3 Tools API Token (Authentication for Tools)

**Purpose:** All **tool** endpoints (Query, File, General) require a valid Tools API Token. The token is a 64-character value that you configure and view in **Plugifity → Licence** (Tools API Token box). If the token is missing or invalid, the API returns an error and does not run the tool.

| Item    | Value |
|--------|--------|
| **Required for** | All endpoints under `/api/query/*`, `/api/file/*`, `/api/general/*` |
| **Not required for** | `GET /api/ping`, `POST /api/license-check` |

**How to send the token (use one of these):**

1. **Authorization header (Bearer):**
   ```http
   Authorization: Bearer YOUR_64_CHARACTER_TOKEN_HERE
   ```

2. **Custom header:**
   ```http
   X-Tools-Api-Token: YOUR_64_CHARACTER_TOKEN_HERE
   ```

**Example request with token:**
```http
POST /wp-json/plugitify/v1/api/query/read
Content-Type: application/json
Authorization: Bearer a1b2c3d4e5f6...

{"sql": "SELECT 1", "bindings": []}
```

**Error when token is missing or invalid:**
```json
{
  "success": false,
  "message": "Invalid or missing tools API token.",
  "data": null
}
```

**Error when token is not configured on the site:**
```json
{
  "success": false,
  "message": "Tools API token is not configured. Please set it in Plugifity → Licence.",
  "data": null
}
```

**Use case:** Secure tool access so only clients that know the token (e.g. your backend or AI agent) can run Query, File, and General tools.

---

## 2. Query Tools (Database)

All query endpoints: **POST** to `{SITE_URL}/wp-json/plugitify/v1/api/query/{action}` with JSON body. **Require Tools API Token** (see [§1.3](#13-tools-api-token-authentication-for-tools)).

### 2.1 Read (SELECT only)

**Purpose:** Run read-only SELECT queries. No data modification.

| Item    | Value |
|--------|--------|
| **URL** | `POST {SITE_URL}/wp-json/plugitify/v1/api/query/read` |
| **Body** | `sql` (string, required), `bindings` (array, optional) |

**Parameters:**
- `sql` – SELECT statement only. Trailing `;` is stripped.
- `bindings` – Ordered list of values for `%s`, `%d`, etc. in the SQL (WordPress prepare style).

**Example request:**
```json
{
  "sql": "SELECT option_name, option_value FROM wp_options WHERE option_name = %s LIMIT 1",
  "bindings": ["siteurl"]
}
```

**Example response:**
```json
{
  "success": true,
  "message": "Query executed.",
  "data": {
    "rows": [
      { "option_name": "siteurl", "option_value": "https://example.com" }
    ],
    "count": 1
  }
}
```

**Use case:** Inspect options, posts, users, custom tables; no writes.

---

### 2.2 Execute (INSERT / UPDATE / DELETE)

**Purpose:** Run write queries. Only INSERT, UPDATE, and DELETE are allowed.

| Item    | Value |
|--------|--------|
| **URL** | `POST {SITE_URL}/wp-json/plugitify/v1/api/query/execute` |
| **Body** | `sql` (string, required), `bindings` (array, optional) |

**Example request (INSERT):**
```json
{
  "sql": "INSERT INTO wp_options (option_name, option_value, autoload) VALUES (%s, %s, %s)",
  "bindings": ["my_option", "value", "no"]
}
```

**Example response:**
```json
{
  "success": true,
  "message": "Query executed.",
  "data": {
    "affected_rows": 1,
    "insert_id": 123
  }
}
```

**Use case:** Insert/update/delete options, post meta, or custom table rows.

---

### 2.3 Create Table

**Purpose:** Create a new database table. Either raw SQL or table name + column definitions.

| Item    | Value |
|--------|--------|
| **URL** | `POST {SITE_URL}/wp-json/plugitify/v1/api/query/create-table` |
| **Body** | Either: `sql` (raw CREATE TABLE) **or** `table` + `columns` |

**Option A – Raw SQL:**
```json
{
  "sql": "CREATE TABLE wp_my_table ( id bigint(20) unsigned NOT NULL AUTO_INCREMENT, name varchar(255) NOT NULL, PRIMARY KEY (id) ) DEFAULT CHARSET=utf8mb4"
}
```

**Option B – Schema (table name with or without WordPress prefix):**
```json
{
  "table": "my_table",
  "columns": [
    { "name": "id", "type": "id" },
    { "name": "title", "type": "string", "length": 255, "nullable": false },
    { "name": "body", "type": "text", "nullable": true },
    { "name": "created_at", "type": "datetime", "nullable": true }
  ]
}
```

**Column types:** `id`, `int`/`integer`, `bigint`, `string`/`varchar`, `text`, `longtext`, `boolean`, `datetime`/`timestamp`, `date`, `decimal` (optional: `total`, `places`). Optional per column: `nullable`, `length`.

**Example response:**
```json
{
  "success": true,
  "message": "Table created.",
  "data": { "table": "wp_my_table" }
}
```

**Use case:** Create custom tables for plugins or migrations.

---

### 2.4 Backup Table

**Purpose:** Backup one table: structure (SHOW CREATE TABLE) and all rows. Saves to `wp-content/plugitify-backups/` and returns metadata.

| Item    | Value |
|--------|--------|
| **URL** | `POST {SITE_URL}/wp-json/plugitify/v1/api/query/backup` |
| **Body** | `table` (string, required) – with or without WordPress table prefix |

**Example request:**
```json
{
  "table": "options"
}
```

**Example response:**
```json
{
  "success": true,
  "message": "Table backed up.",
  "data": {
    "table": "wp_options",
    "row_count": 150,
    "saved_to": "C:\\path\\to\\wp-content\\plugitify-backups\\backup-wp_options-2026-02-15-1.json"
  }
}
```

**File naming:** `backup-{table_name}-{Y-m-d}-{n}.json` in `wp-content/plugitify-backups/`.

**Use case:** Before risky changes, backup a table; then restore via `query/restore` if needed.

---

### 2.5 List Backups

**Purpose:** List all backup files in `wp-content/plugitify-backups/` with path and metadata.

| Item    | Value |
|--------|--------|
| **URL** | `POST {SITE_URL}/wp-json/plugitify/v1/api/query/backup-list` |
| **Body** | `{}` or empty |

**Example response:**
```json
{
  "success": true,
  "message": "Backups listed.",
  "data": {
    "backups": [
      {
        "path": "C:\\...\\plugitify-backups\\backup-wp_options-2026-02-15-1.json",
        "filename": "backup-wp_options-2026-02-15-1.json",
        "table": "wp_options",
        "row_count": 150,
        "backup_at": "2026-02-15 12:00:00"
      }
    ],
    "count": 1
  }
}
```

**Use case:** Choose which backup file to pass to `query/restore` (via `file` parameter).

---

### 2.6 Restore Table

**Purpose:** Restore a table from a backup file or from inline `create_sql` + `rows`. Optionally drop existing table first.

| Item    | Value |
|--------|--------|
| **URL** | `POST {SITE_URL}/wp-json/plugitify/v1/api/query/restore` |
| **Body** | One of: `file` (path or filename in backup dir), or `create_sql` + `rows`, or `backup` (object). Optional: `drop_first` (bool, default true). |

**Option A – From backup file (path or filename):**
```json
{
  "file": "backup-wp_options-2026-02-15-1.json",
  "drop_first": true
}
```

**Option B – Inline SQL and rows:**
```json
{
  "create_sql": "CREATE TABLE `wp_my_table` (...)",
  "rows": [
    { "id": 1, "name": "a" },
    { "id": 2, "name": "b" }
  ],
  "drop_first": true
}
```

**Option C – Full backup object (e.g. from query/backup response):**
```json
{
  "backup": {
    "table": "wp_options",
    "create_sql": "CREATE TABLE `wp_options` (...)",
    "rows": [ ... ],
    "row_count": 150
  },
  "drop_first": true
}
```

**Example response:**
```json
{
  "success": true,
  "message": "Table restored.",
  "data": { "inserted_rows": 150 }
}
```

**Use case:** Roll back after failed changes using a backup from `query/backup` or `query/backup-list`.

---

### 2.7 List Tables

**Purpose:** List all database tables (and their columns). Optionally filter to WordPress-prefixed tables only.

| Item    | Value |
|--------|--------|
| **URL** | `POST {SITE_URL}/wp-json/plugitify/v1/api/query/tables` |
| **Body** | `{}` or `{ "prefix_only": true }` |

**Example request (all tables):**
```json
{}
```

**Example request (only wp_* tables):**
```json
{
  "prefix_only": true
}
```

**Example response:**
```json
{
  "success": true,
  "message": "Tables listed.",
  "data": {
    "tables": [
      {
        "name": "wp_options",
        "columns": [
          { "name": "option_id", "type": "bigint", "nullable": false, "key": "PRI", "default": null, "extra": "auto_increment" },
          { "name": "option_name", "type": "varchar", "nullable": false, "key": "", "default": null, "extra": "" },
          { "name": "option_value", "type": "longtext", "nullable": false, "key": "", "default": null, "extra": "" }
        ]
      }
    ],
    "count": 1
  }
}
```

**Use case:** Discover schema before writing `query/read` or `query/execute`; understand table structure.

---

## 3. File Tools

All file endpoints: **POST** to `{SITE_URL}/wp-json/plugitify/v1/api/file/{action}`. **Require Tools API Token** (see [§1.3](#13-tools-api-token-authentication-for-tools)). Paths are relative to WordPress root (ABSPATH) or absolute; must stay under ABSPATH.

### 3.1 WordPress Root Path

**Purpose:** Get the filesystem path of the WordPress root (ABSPATH).

| Item    | Value |
|--------|--------|
| **URL** | `POST {SITE_URL}/wp-json/plugitify/v1/api/file/wp-path` |
| **Body** | `{}` or empty |

**Example response:**
```json
{
  "success": true,
  "message": "",
  "data": {
    "path": "C:\\wamp64\\www\\wpagentify\\wordpress\\"
  }
}
```

**Use case:** Resolve relative paths or show the agent where WordPress is installed.

---

### 3.2 List Directory

**Purpose:** List directories and files in a given path (under WordPress root).

| Item    | Value |
|--------|--------|
| **URL** | `POST {SITE_URL}/wp-json/plugitify/v1/api/file/list-directory` |
| **Body** | `path` (string, required) |

**Example request:**
```json
{
  "path": "wp-content/plugins/plugitify"
}
```

**Example response:**
```json
{
  "success": true,
  "message": "Directory listed.",
  "data": {
    "path": "C:\\...\\plugitify",
    "directories": ["src", "view", "assets", "docs"],
    "files": ["plugifity.php", "README.md"]
  }
}
```

**Use case:** Browse plugin/theme structure, find config files, locate code.

---

### 3.3 Read File

**Purpose:** Read full contents of a file under WordPress root.

| Item    | Value |
|--------|--------|
| **URL** | `POST {SITE_URL}/wp-json/plugitify/v1/api/file/read` |
| **Body** | `path` (string, required) |

**Example request:**
```json
{
  "path": "wp-content/plugins/plugitify/plugifity.php"
}
```

**Example response:**
```json
{
  "success": true,
  "message": "File read.",
  "data": {
    "path": "C:\\...\\plugifity.php",
    "content": "<?php\n..."
  }
}
```

**Use case:** Read PHP/config files to understand or modify code.

---

### 3.4 Grep (Search in Files)

**Purpose:** Search for a pattern in files under a directory (recursive). Like a code search.

| Item    | Value |
|--------|--------|
| **URL** | `POST {SITE_URL}/wp-json/plugitify/v1/api/file/grep` |
| **Body** | `path` (string), `pattern` (string). Optional: `regex` (bool), `case_sensitive` (bool) |

**Example request (literal text):**
```json
{
  "path": "wp-content/plugins/plugitify/src",
  "pattern": "Response::success"
}
```

**Example request (regex, case-sensitive):**
```json
{
  "path": "wp-content/plugins/plugitify/src",
  "pattern": "public function \\w+\\(",
  "regex": true,
  "case_sensitive": true
}
```

**Example response:**
```json
{
  "success": true,
  "message": "Search completed.",
  "data": {
    "matches": [
      {
        "path": "C:\\...\\File.php",
        "line_number": 95,
        "line": "    public function grep(Request $request): array"
      }
    ]
  }
}
```

**Use case:** Find where a function/string is used before editing.

---

### 3.5 Create File

**Purpose:** Create an empty file at the given path. Parent directories are created if needed.

| Item    | Value |
|--------|--------|
| **URL** | `POST {SITE_URL}/wp-json/plugitify/v1/api/file/create` |
| **Body** | `path` (string, required) |

**Example request:**
```json
{
  "path": "wp-content/plugins/plugitify/my-file.txt"
}
```

**Use case:** Create new files; then use `file/replace-content` to write content.

---

### 3.6 Create Folder

**Purpose:** Create a directory (and parents if needed) under WordPress root.

| Item    | Value |
|--------|--------|
| **URL** | `POST {SITE_URL}/wp-json/plugitify/v1/api/file/create-folder` |
| **Body** | `path` (string, required) |

**Example request:**
```json
{
  "path": "wp-content/plugins/plugitify/assets/custom"
}
```

**Use case:** Add new directories for assets or modules.

---

### 3.7 Replace Content

**Purpose:** Overwrite entire file content with new text.

| Item    | Value |
|--------|--------|
| **URL** | `POST {SITE_URL}/wp-json/plugitify/v1/api/file/replace-content` |
| **Body** | `path` (string), `content` (string). `content` can also be sent as raw body. |

**Example request:**
```json
{
  "path": "wp-content/plugins/plugitify/test.txt",
  "content": "Line 1\nLine 2\nLine 3"
}
```

**Use case:** Write or fully rewrite a file (e.g. config, new PHP file).

---

### 3.8 Replace Line

**Purpose:** Replace a single line in a file by 1-based line number.

| Item    | Value |
|--------|--------|
| **URL** | `POST {SITE_URL}/wp-json/plugitify/v1/api/file/replace-line` |
| **Body** | `path` (string), `line_number` (int, >= 1), `content` (string) |

**Example request:**
```json
{
  "path": "wp-content/plugins/plugitify/test.txt",
  "line_number": 2,
  "content": "Updated second line"
}
```

**Use case:** Small, surgical edits (e.g. change one constant or one line of code).

---

### 3.9 Search and Replace

**Purpose:** Replace a text block with another in a file (like Cursor). No line numbers or full-file rewrite. First occurrence only, or all occurrences.

| Item    | Value |
|--------|--------|
| **URL** | `POST {SITE_URL}/wp-json/plugitify/v1/api/file/search-replace` |
| **Body** | `path` (string), `old_string` (string), `new_string` (string). Optional: `replace_all` (bool, default false) |

**Example request (first occurrence only):**
```json
{
  "path": "wp-content/plugins/plugitify/src/Service/Tools/File.php",
  "old_string": "ApiRouter::post('file/delete', [$this, 'delete'])->name('api.tools.file.delete');\n    }",
  "new_string": "ApiRouter::post('file/delete', [$this, 'delete'])->name('api.tools.file.delete');\n        ApiRouter::post('file/search-replace', [$this, 'searchReplace'])->name('api.tools.file.search-replace');\n    }",
  "replace_all": false
}
```

**Example request (replace all):**
```json
{
  "path": "wp-content/plugins/plugitify/config.php",
  "old_string": "OLD_CONSTANT",
  "new_string": "NEW_CONSTANT",
  "replace_all": true
}
```

**Example response (success):**
```json
{
  "success": true,
  "message": "Search and replace completed.",
  "data": { "replacements_count": 1 }
}
```

**Example response (old_string not found):**
```json
{
  "success": false,
  "message": "old_string not found in file.",
  "data": { "path": "C:\\...\\File.php" }
}
```

**Use case:** Edit a specific code block without sending the whole file; fewer tokens and fewer errors than multiple `replace-line` or full `replace-content`.

---

### 3.10 Read File Range

**Purpose:** Read only a range of lines from a file (for large files). Saves tokens and time when only a section is needed.

| Item    | Value |
|--------|--------|
| **URL** | `POST {SITE_URL}/wp-json/plugitify/v1/api/file/read-range` |
| **Body** | `path` (string), `offset` (int, 1-based start line), optional `limit` (int, default 100) |

**Example request:**
```json
{
  "path": "wp-content/plugins/plugitify/src/Service/Tools/File.php",
  "offset": 45,
  "limit": 60
}
```

**Example response:**
```json
{
  "success": true,
  "message": "File range read.",
  "data": {
    "path": "C:\\...\\File.php",
    "content": "lines 45–104 as single string with \\n",
    "offset": 45,
    "limit": 60,
    "total_lines": 480
  }
}
```

**Use case:** Inspect one function or block (e.g. lines 50–80) without loading the entire file.

---

### 3.11 Create File with Content

**Purpose:** Create a new file with content in a single request. Parent directories are created if needed. Fails if file already exists.

| Item    | Value |
|--------|--------|
| **URL** | `POST {SITE_URL}/wp-json/plugitify/v1/api/file/create-with-content` |
| **Body** | `path` (string), `content` (string). `content` can also be sent as raw body. |

**Example request:**
```json
{
  "path": "wp-content/plugins/plugitify/my-module/helper.php",
  "content": "<?php\nnamespace MyModule;\n\nfunction helper() {\n    return true;\n}\n"
}
```

**Example response:**
```json
{
  "success": true,
  "message": "File created with content.",
  "data": { "path": "C:\\...\\helper.php" }
}
```

**Use case:** One round-trip instead of `file/create` + `file/replace-content`.

---

### 3.12 Replace Lines

**Purpose:** Replace a range of lines (by 1-based start and end line) with new content in one request.

| Item    | Value |
|--------|--------|
| **URL** | `POST {SITE_URL}/wp-json/plugitify/v1/api/file/replace-lines` |
| **Body** | `path` (string), `start_line` (int, 1-based), `end_line` (int, 1-based), `content` (string, can be multi-line) |

**Example request:**
```json
{
  "path": "wp-content/plugins/plugitify/config.php",
  "start_line": 10,
  "end_line": 15,
  "content": "define('NEW_OPTION', true);\ndefine('ANOTHER', 42);"
}
```

**Example response:**
```json
{
  "success": true,
  "message": "Lines replaced.",
  "data": { "path": "C:\\...\\config.php" }
}
```

**Use case:** Edit a contiguous block of lines without sending the whole file or calling `replace-line` multiple times.

---

### 3.13 Delete File or Folder

**Purpose:** Delete a file or a directory. Directories are removed recursively. WordPress root cannot be deleted.

| Item    | Value |
|--------|--------|
| **URL** | `POST {SITE_URL}/wp-json/plugitify/v1/api/file/delete` |
| **Body** | `path` (string, required) |

**Example request (file):**
```json
{
  "path": "wp-content/plugins/plugitify/temp.txt"
}
```

**Example request (folder and all contents):**
```json
{
  "path": "wp-content/plugins/plugitify/temp-folder"
}
```

**Use case:** Remove generated files, temp folders, or obsolete assets.

---

## 4. General Tools

All general endpoints: **POST** to `{SITE_URL}/wp-json/plugitify/v1/api/general/{action}`. **Require Tools API Token** (see [§1.3](#13-tools-api-token-authentication-for-tools)).

### 4.1 List Plugins

**Purpose:** List all installed plugins with name, description, version, path, and active status.

| Item    | Value |
|--------|--------|
| **URL** | `POST {SITE_URL}/wp-json/plugitify/v1/api/general/plugins` |
| **Body** | `{}` or empty |

**Example response:**
```json
{
  "success": true,
  "message": "Plugins list.",
  "data": {
    "plugins": [
      {
        "name": "Plugitify",
        "description": "...",
        "version": "1.0.0",
        "author": "...",
        "plugin_uri": "",
        "path": "plugitify/plugifity.php",
        "is_active": true
      }
    ]
  }
}
```

**Use case:** See what is installed and active before changing code or debugging.

---

### 4.2 List Themes

**Purpose:** List all installed themes with name, description, version, path, template, and active status.

| Item    | Value |
|--------|--------|
| **URL** | `POST {SITE_URL}/wp-json/plugitify/v1/api/general/themes` |
| **Body** | `{}` or empty |

**Example response:**
```json
{
  "success": true,
  "message": "Themes list.",
  "data": {
    "themes": [
      {
        "name": "Twenty Twenty-Four",
        "description": "...",
        "version": "1.0",
        "author": "...",
        "theme_uri": "",
        "path": "C:\\...\\wp-content\\themes\\twentytwentyfour",
        "template": "twentytwentyfour",
        "is_active": true
      }
    ]
  }
}
```

**Use case:** Identify active theme and theme paths for edits.

---

### 4.3 Debug Settings

**Purpose:** Read or update WP_DEBUG, WP_DEBUG_LOG, WP_DEBUG_DISPLAY in `wp-config.php`.

| Item    | Value |
|--------|--------|
| **URL** | `POST {SITE_URL}/wp-json/plugitify/v1/api/general/debug` |
| **Body** | Empty/`{}` = read only. To update: `enabled`, `log_to_file`, `display` (booleans). |

**Read current state:**
```json
{}
```

**Set debug on, log to file, do not display:**
```json
{
  "enabled": true,
  "log_to_file": true,
  "display": false
}
```

**Example response:**
```json
{
  "success": true,
  "message": "Debug settings.",
  "data": {
    "enabled": true,
    "log_to_file": true,
    "display": false
  }
}
```

**Use case:** Enable logging for troubleshooting, then read log via `general/log`.

---

### 4.4 Read Log File

**Purpose:** Read contents of a log file. Default: `wp-content/debug.log`. Optional custom path under WordPress root.

| Item    | Value |
|--------|--------|
| **URL** | `POST {SITE_URL}/wp-json/plugitify/v1/api/general/log` |
| **Body** | `{}` for default, or `{ "path": "wp-content/debug.log" }` for a specific file |

**Example response:**
```json
{
  "success": true,
  "message": "Log file read.",
  "data": {
    "path": "C:\\...\\wp-content\\debug.log",
    "content": "[15-Feb-2026 12:00:00 UTC] PHP Notice: ..."
  }
}
```

**Use case:** Inspect PHP/WordPress errors after enabling debug with `general/debug`.

---

### 4.5 Site URLs

**Purpose:** Get `siteurl` and `home` from the WordPress options table.

| Item    | Value |
|--------|--------|
| **URL** | `POST {SITE_URL}/wp-json/plugitify/v1/api/general/site-urls` |
| **Body** | `{}` or empty |

**Example response:**
```json
{
  "success": true,
  "message": "",
  "data": {
    "siteurl": "https://example.com",
    "home": "https://example.com"
  }
}
```

**Use case:** Build correct API base URL or links in agent responses.

---

### 4.6 Read API Requests

**Purpose:** Read the last 200 API request records from the plugin database (table `plugifity_api_requests`). No pagination; returns a fixed slice of the most recent entries.

| Item    | Value |
|--------|--------|
| **URL** | `POST {SITE_URL}/wp-json/plugitify/v1/api/general/api-requests` |
| **Body** | `{}` or empty |

**Example response:**
```json
{
  "success": true,
  "message": "API requests.",
  "data": {
    "items": [
      {
        "id": 1,
        "url": "general/plugins",
        "title": "List plugins",
        "description": null,
        "from_source": "api.general",
        "details": null,
        "created_at": "2026-02-19 10:00:00",
        "updated_at": "2026-02-19 10:00:00"
      }
    ]
  }
}
```

**Use case:** Inspect recent API tool usage and request history stored by the plugin.

---

### 4.7 Read Changes

**Purpose:** Read the last 200 change records from the plugin database (table `plugifity_changes`). No pagination; returns the 200 most recent entries ordered by `created_at` DESC.

| Item    | Value |
|--------|--------|
| **URL** | `POST {SITE_URL}/wp-json/plugitify/v1/api/general/changes` |
| **Body** | `{}` or empty |

**Example response:**
```json
{
  "success": true,
  "message": "Changes.",
  "data": {
    "items": [
      {
        "id": 1,
        "type": "plugin_created",
        "from_value": null,
        "to_value": "C:\\...\\plugins\\my-plugin",
        "details": "{\"plugin_name\":\"My Plugin\",\"folder\":\"my-plugin\"}",
        "created_at": "2026-02-19 10:00:00",
        "updated_at": "2026-02-19 10:00:00"
      }
    ]
  }
}
```

**Use case:** Review recent changes (plugin/theme create/delete, debug updates, etc.) recorded by the plugin.

---

### 4.8 Read Errors

**Purpose:** Read the last 200 error log entries from the plugin database (table `plugifity_logs` where `type = 'error'`). No pagination; returns the 200 most recent error records.

| Item    | Value |
|--------|--------|
| **URL** | `POST {SITE_URL}/wp-json/plugitify/v1/api/general/errors` |
| **Body** | `{}` or empty |

**Example response:**
```json
{
  "success": true,
  "message": "Errors.",
  "data": {
    "items": [
      {
        "id": 1,
        "type": "error",
        "message": "Plugin folder not found.",
        "context": "{\"path\":\"C:\\...\\plugins\\missing\"}",
        "created_at": "2026-02-19 10:00:00",
        "updated_at": "2026-02-19 10:00:00"
      }
    ]
  }
}
```

**Use case:** Inspect recent errors logged by the plugin (e.g. failed file or API operations).

---

### 4.9 Read Logs

**Purpose:** Read the last 200 log entries (all types) from the plugin database (table `plugifity_logs`). No pagination; returns the 200 most recent log records.

| Item    | Value |
|--------|--------|
| **URL** | `POST {SITE_URL}/wp-json/plugitify/v1/api/general/logs` |
| **Body** | `{}` or empty |

**Example response:**
```json
{
  "success": true,
  "message": "Logs.",
  "data": {
    "items": [
      {
        "id": 1,
        "type": "info",
        "message": "Plugins list.",
        "context": "{\"count\":5}",
        "created_at": "2026-02-19 10:00:00",
        "updated_at": "2026-02-19 10:00:00"
      }
    ]
  }
}
```

**Use case:** Inspect recent activity (info, error, etc.) recorded by the plugin across all tools.

---

### 4.10 Create Plugin

**Purpose:** Create a new WordPress plugin by copying the built-in template to `wp-content/plugins/{folder_name}/`. The template includes a main file (with plugin headers), `classes/`, `assets/css/`, `assets/js/`, and all class/function names use a consistent prefix derived from the folder name.

| Item    | Value |
|--------|--------|
| **URL** | `POST {SITE_URL}/wp-json/plugitify/v1/api/general/create-plugin` |
| **Body** | `plugin_name` (string, required), `folder_name` (string, required), `version` (string, optional, default `1.0.0`) |

**Parameters:**
- `plugin_name` – Display name of the plugin (e.g. "My Awesome Plugin").
- `folder_name` – Slug for the plugin folder (e.g. "my-awesome-plugin"). Must be safe for directory names; it is sanitized (lowercase, alphanumeric, dashes).
- `version` – Plugin version string; default `1.0.0`.

**Example request:**
```json
{
  "plugin_name": "My Awesome Plugin",
  "folder_name": "my-awesome-plugin",
  "version": "1.0.0"
}
```

**Example response:**
```json
{
  "success": true,
  "message": "Plugin created.",
  "data": {
    "path": "C:\\...\\wp-content\\plugins\\my-awesome-plugin",
    "folder_name": "my-awesome-plugin",
    "main_file": "my-awesome-plugin.php"
  }
}
```

**Use case:** Scaffold a new plugin with prefixed classes and assets without writing files manually.

---

### 4.11 Create Theme

**Purpose:** Create a new WordPress theme by copying the built-in template to `wp-content/themes/{folder_name}/`. The template includes `style.css` (with theme headers), `functions.php`, `index.php`, `header.php`, `footer.php`, `classes/`, and `assets/`. All class/function names use a consistent prefix derived from the folder name.

| Item    | Value |
|--------|--------|
| **URL** | `POST {SITE_URL}/wp-json/plugitify/v1/api/general/create-theme` |
| **Body** | `theme_name` (string, required), `folder_name` (string, required), `version` (string, optional, default `1.0.0`) |

**Parameters:**
- `theme_name` – Display name of the theme (e.g. "My Awesome Theme").
- `folder_name` – Slug for the theme folder (e.g. "my-awesome-theme"). Must be safe for directory names; it is sanitized.
- `version` – Theme version string; default `1.0.0`.

**Example request:**
```json
{
  "theme_name": "My Awesome Theme",
  "folder_name": "my-awesome-theme",
  "version": "1.0.0"
}
```

**Example response:**
```json
{
  "success": true,
  "message": "Theme created.",
  "data": {
    "path": "C:\\...\\wp-content\\themes\\my-awesome-theme",
    "folder_name": "my-awesome-theme"
  }
}
```

**Use case:** Scaffold a new theme with prefixed classes and assets without writing files manually.

---

### 4.12 Delete Plugin

**Purpose:** Delete a plugin by its folder name (slug). The plugin is deactivated first if it is active, then the entire plugin directory under `wp-content/plugins/{folder_name}/` is removed recursively.

| Item    | Value |
|--------|--------|
| **URL** | `POST {SITE_URL}/wp-json/plugitify/v1/api/general/delete-plugin` |
| **Body** | `folder_name` (string, required) – slug of the plugin (same as the folder name under `wp-content/plugins`) |

**Example request:**
```json
{
  "folder_name": "my-awesome-plugin"
}
```

**Example response:**
```json
{
  "success": true,
  "message": "Plugin deleted.",
  "data": {
    "path": "C:\\...\\wp-content\\plugins\\my-awesome-plugin"
  }
}
```

**Use case:** Remove a plugin completely (e.g. after testing or when replacing with another solution).

---

### 4.13 Delete Theme

**Purpose:** Delete a theme by its folder name (slug). The entire theme directory under `wp-content/themes/{folder_name}/` is removed recursively. **Fails if the theme is currently active;** switch to another theme first.

| Item    | Value |
|--------|--------|
| **URL** | `POST {SITE_URL}/wp-json/plugitify/v1/api/general/delete-theme` |
| **Body** | `folder_name` (string, required) – slug of the theme (same as the folder name under `wp-content/themes`) |

**Example request:**
```json
{
  "folder_name": "my-awesome-theme"
}
```

**Example response:**
```json
{
  "success": true,
  "message": "Theme deleted.",
  "data": {
    "path": "C:\\...\\wp-content\\themes\\my-awesome-theme"
  }
}
```

**Error when active theme:** If the theme is active, the API returns `success: false` with a message like "Cannot delete the active theme. Switch to another theme first."

**Use case:** Remove a theme completely after switching to another theme.

---

## Quick Reference Table

**Note:** All **Query**, **File**, and **General** endpoints require the [Tools API Token](#13-tools-api-token-authentication-for-tools) in the request header. **Core** endpoints (ping, license-check) do not.

| Category | Endpoint | Method | Purpose |
|----------|----------|--------|---------|
| **Core** | `/wp-json/plugitify/v1/api/ping` | GET | Health check |
| **Core** | `/wp-json/plugitify/v1/api/license-check` | POST | Validate license key |
| **Query** | `/wp-json/plugitify/v1/api/query/read` | POST | Run SELECT |
| **Query** | `/wp-json/plugitify/v1/api/query/execute` | POST | Run INSERT/UPDATE/DELETE |
| **Query** | `/wp-json/plugitify/v1/api/query/create-table` | POST | Create table |
| **Query** | `/wp-json/plugitify/v1/api/query/backup` | POST | Backup table to file |
| **Query** | `/wp-json/plugitify/v1/api/query/backup-list` | POST | List backup files |
| **Query** | `/wp-json/plugitify/v1/api/query/restore` | POST | Restore from backup |
| **Query** | `/wp-json/plugitify/v1/api/query/tables` | POST | List tables + columns |
| **File** | `/wp-json/plugitify/v1/api/file/wp-path` | POST | Get WordPress root path |
| **File** | `/wp-json/plugitify/v1/api/file/list-directory` | POST | List dir contents |
| **File** | `/wp-json/plugitify/v1/api/file/read` | POST | Read file |
| **File** | `/wp-json/plugitify/v1/api/file/grep` | POST | Search in files |
| **File** | `/wp-json/plugitify/v1/api/file/create` | POST | Create empty file |
| **File** | `/wp-json/plugitify/v1/api/file/create-folder` | POST | Create directory |
| **File** | `/wp-json/plugitify/v1/api/file/replace-content` | POST | Overwrite file content |
| **File** | `/wp-json/plugitify/v1/api/file/replace-line` | POST | Replace one line |
| **File** | `/wp-json/plugitify/v1/api/file/search-replace` | POST | Search and replace text in file |
| **File** | `/wp-json/plugitify/v1/api/file/read-range` | POST | Read range of lines |
| **File** | `/wp-json/plugitify/v1/api/file/create-with-content` | POST | Create file with content |
| **File** | `/wp-json/plugitify/v1/api/file/replace-lines` | POST | Replace range of lines |
| **File** | `/wp-json/plugitify/v1/api/file/delete` | POST | Delete file or folder |
| **General** | `/wp-json/plugitify/v1/api/general/plugins` | POST | List plugins |
| **General** | `/wp-json/plugitify/v1/api/general/themes` | POST | List themes |
| **General** | `/wp-json/plugitify/v1/api/general/debug` | POST | Get/set debug in wp-config |
| **General** | `/wp-json/plugitify/v1/api/general/log` | POST | Read log file |
| **General** | `/wp-json/plugitify/v1/api/general/site-urls` | POST | Get siteurl & home |
| **General** | `/wp-json/plugitify/v1/api/general/api-requests` | POST | Last 200 API request records (no pagination) |
| **General** | `/wp-json/plugitify/v1/api/general/changes` | POST | Last 200 change records (no pagination) |
| **General** | `/wp-json/plugitify/v1/api/general/errors` | POST | Last 200 error log entries (no pagination) |
| **General** | `/wp-json/plugitify/v1/api/general/logs` | POST | Last 200 log entries (no pagination) |
| **General** | `/wp-json/plugitify/v1/api/general/create-plugin` | POST | Create plugin from template |
| **General** | `/wp-json/plugitify/v1/api/general/create-theme` | POST | Create theme from template |
| **General** | `/wp-json/plugitify/v1/api/general/delete-plugin` | POST | Delete plugin by folder name |
| **General** | `/wp-json/plugitify/v1/api/general/delete-theme` | POST | Delete theme by folder name |

---

## Using This With an AI/Backend Model

1. **Base URL:** Set `{SITE_URL}/wp-json/plugitify/v1/api` as the API base (replace `{SITE_URL}` with the real site URL, e.g. from `general/site-urls`).
2. **Tools API Token:** For every request to a **tool** endpoint (Query, File, General), send the 64-character Tools API Token in a header: `Authorization: Bearer <token>` or `X-Tools-Api-Token: <token>`. The token is shown in **Plugifity → Licence** (Tools API Token box). Core endpoints (ping, license-check) do not require this token.
3. **Tools:** Expose each endpoint as a “tool” or “function” with the parameters described above. The model can then choose the right tool (e.g. `file/read` then `file/replace-line`) for the task.
4. **Order:** For file edits: use `file/read` or `file/read-range` for large files → then `file/search-replace` (preferred for text blocks), `file/replace-lines` (for line ranges), or `file/replace-content` / `file/replace-line` as needed. Use `file/create-with-content` to create a file with content in one call. For DB: `query/tables` or `query/read` → then `query/execute` or `query/backup` / `query/restore` as needed.
5. **Errors:** On `success: false`, use `message` and `data` to inform the user or retry with corrected parameters.

This reference is the single source for all Plugitify backend APIs and tools; give this document to the model that will call the API so it knows how to call and use each endpoint.
