# تست API فایل – همه با متد POST و بدنه JSON

**آدرس پایه (با سایت خودت عوض کن):**
```
http://localhost/wpagentify/wordpress/wp-json/plugitify/v1/api
```

**همهٔ درخواست‌ها:** متد **POST** و هدر **Content-Type: application/json** و بدنه **JSON**.

---

## 1. مسیر وردپرس (WP Path)

**URL:** `POST .../api/file/wp-path`  
**ورودی:** بدنه خالی یا `{}`

**JSON ورودی (نمونه):**
```json
{}
```

**درخواست کامل (PowerShell):**
```powershell
$BASE = "http://localhost/wpagentify/wordpress/wp-json/plugitify/v1/api"
Invoke-RestMethod -Uri "$BASE/file/wp-path" -Method Post -ContentType "application/json" -Body '{}'
```

**پاسخ نمونه:**
```json
{
  "success": true,
  "message": "",
  "data": {
    "path": "C:\\wamp64\\www\\wpagentify\\wordpress\\"
  }
}
```

---

## 2. لیست دایرکتوری (List Directory)

**URL:** `POST .../api/file/list-directory`

**JSON ورودی (نمونه):**
```json
{
  "path": "wp-content/plugins/plugitify"
}
```

**درخواست کامل (PowerShell):**
```powershell
Invoke-RestMethod -Uri "$BASE/file/list-directory" -Method Post -ContentType "application/json" -Body '{"path":"wp-content/plugins/plugitify"}'
```

**پاسخ نمونه:**
```json
{
  "success": true,
  "message": "Directory listed.",
  "data": {
    "path": "C:\\wamp64\\www\\wpagentify\\wordpress\\wp-content\\plugins\\plugitify",
    "directories": ["src", "view", "assets", "docs"],
    "files": ["plugifity.php", "README.md"]
  }
}
```

---

## 3. خواندن فایل (Read File)

**URL:** `POST .../api/file/read`

**JSON ورودی (نمونه)** – مسیر نسبی به روت وردپرس (نام فایل اصلی پلاگین: **plugifity.php**):
```json
{
  "path": "wp-content/plugins/plugitify/plugifity.php"
}
```

**درخواست کامل (PowerShell):**
```powershell
Invoke-RestMethod -Uri "$BASE/file/read" -Method Post -ContentType "application/json" -Body '{"path":"wp-content/plugins/plugitify/plugifity.php"}'
```

**پاسخ نمونه:**
```json
{
  "success": true,
  "message": "File read.",
  "data": {
    "path": "C:\\wamp64\\...\\plugifity.php",
    "content": "<?php\n..."
  }
}
```

---

## 4. Grep (جستجو در فایل‌ها)

**URL:** `POST .../api/file/grep`

**JSON ورودی – جستجوی ساده (متن ثابت):**
```json
{
  "path": "wp-content/plugins/plugitify/src",
  "pattern": "AbstractService"
}
```

**JSON ورودی – با regex:**
```json
{
  "path": "wp-content/plugins/plugitify/src",
  "pattern": "public function",
  "regex": true
}
```

**JSON ورودی – حساس به حروف بزرگ/کوچک:**
```json
{
  "path": "wp-content/plugins/plugitify/src",
  "pattern": "Response",
  "case_sensitive": true
}
```

**درخواست کامل (PowerShell):**
```powershell
Invoke-RestMethod -Uri "$BASE/file/grep" -Method Post -ContentType "application/json" -Body '{"path":"wp-content/plugins/plugitify/src","pattern":"Response"}'
```

**پاسخ نمونه:**
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

---

## 5. ایجاد پوشه (Create Folder)

**URL:** `POST .../api/file/create-folder`

**JSON ورودی (نمونه):**
```json
{
  "path": "wp-content/plugins/plugitify/test-folder"
}
```

**درخواست کامل (PowerShell):**
```powershell
Invoke-RestMethod -Uri "$BASE/file/create-folder" -Method Post -ContentType "application/json" -Body '{"path":"wp-content/plugins/plugitify/test-folder"}'
```

**پاسخ نمونه:**
```json
{
  "success": true,
  "message": "Folder created."
}
```

---

## 6. ایجاد فایل خالی (Create File)

**URL:** `POST .../api/file/create`

**JSON ورودی (نمونه):**
```json
{
  "path": "wp-content/plugins/plugitify/test-folder/empty.txt"
}
```

**درخواست کامل (PowerShell):**
```powershell
Invoke-RestMethod -Uri "$BASE/file/create" -Method Post -ContentType "application/json" -Body '{"path":"wp-content/plugins/plugitify/test-folder/empty.txt"}'
```

**پاسخ نمونه:**
```json
{
  "success": true,
  "message": "File created."
}
```

---

## 7. جایگزینی کل محتوای فایل (Replace Content)

**URL:** `POST .../api/file/replace-content`

**JSON ورودی (نمونه):**
```json
{
  "path": "wp-content/plugins/plugitify/test-folder/empty.txt",
  "content": "Hello World\nLine 2"
}
```

**درخواست کامل (PowerShell):**
```powershell
Invoke-RestMethod -Uri "$BASE/file/replace-content" -Method Post -ContentType "application/json" -Body '{"path":"wp-content/plugins/plugitify/test-folder/empty.txt","content":"Hello World\nLine 2"}'
```

**پاسخ نمونه:**
```json
{
  "success": true,
  "message": "File content replaced."
}
```

---

## 8. تغییر یک خط (Replace Line)

**URL:** `POST .../api/file/replace-line`

**JSON ورودی (نمونه):**
```json
{
  "path": "wp-content/plugins/plugitify/test-folder/empty.txt",
  "line_number": 2,
  "content": "Line 2 updated"
}
```

**درخواست کامل (PowerShell):**
```powershell
Invoke-RestMethod -Uri "$BASE/file/replace-line" -Method Post -ContentType "application/json" -Body '{"path":"wp-content/plugins/plugitify/test-folder/empty.txt","line_number":2,"content":"Line 2 updated"}'
```

**پاسخ نمونه:**
```json
{
  "success": true,
  "message": "Line replaced."
}
```

---

## 9. حذف فایل یا پوشه (Delete)

**URL:** `POST .../api/file/delete`

یک endpoint برای هر دو: اگر مسیر به **فایل** باشد، فایل حذف می‌شود؛ اگر به **پوشه** باشد، پوشه و تمام محتویاتش به‌صورت بازگشتی حذف می‌شود. حذف روت وردپرس مجاز نیست.

**JSON ورودی – حذف فایل:**
```json
{
  "path": "wp-content/plugins/plugitify/test-folder/empty.txt"
}
```

**JSON ورودی – حذف پوشه:**
```json
{
  "path": "wp-content/plugins/plugitify/test-folder"
}
```

**درخواست کامل (PowerShell):**
```powershell
# حذف فایل
Invoke-RestMethod -Uri "$BASE/file/delete" -Method Post -ContentType "application/json" -Body '{"path":"wp-content/plugins/plugitify/test-folder/empty.txt"}'

# حذف پوشه (و همهٔ محتویاتش)
Invoke-RestMethod -Uri "$BASE/file/delete" -Method Post -ContentType "application/json" -Body '{"path":"wp-content/plugins/plugitify/test-folder"}'
```

**پاسخ نمونه:**
```json
{
  "success": true,
  "message": "File deleted."
}
```
یا برای پوشه: `"message": "Directory deleted."`

---

# APIهای General (پلاگین‌ها، تم‌ها، دیباگ، آدرس سایت، لاگ)

همه با **POST** و **Content-Type: application/json**. همان `$BASE` استفاده می‌شود.

---

## 10. لیست پلاگین‌ها (Plugins)

**URL:** `POST .../api/general/plugins`

لیست همهٔ پلاگین‌های نصب‌شده با جزئیات: نام، توضیحات، ورژن، مسیر، وضعیت فعال/غیرفعال.

**JSON ورودی (نمونه):**
```json
{}
```

**درخواست کامل (PowerShell):**
```powershell
Invoke-RestMethod -Uri "$BASE/general/plugins" -Method Post -ContentType "application/json" -Body '{}'
```

**پاسخ نمونه:**
```json
{
  "success": true,
  "message": "Plugins list.",
  "data": {
    "plugins": [
      {
        "name": "Plugifity",
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

---

## 11. لیست تم‌ها (Themes)

**URL:** `POST .../api/general/themes`

لیست همهٔ تم‌های نصب‌شده با جزئیات: نام، توضیحات، ورژن، مسیر، تم فعال.

**JSON ورودی (نمونه):**
```json
{}
```

**درخواست کامل (PowerShell):**
```powershell
Invoke-RestMethod -Uri "$BASE/general/themes" -Method Post -ContentType "application/json" -Body '{}'
```

**پاسخ نمونه:**
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

---

## 12. تنظیمات دیباگ (Debug)

**URL:** `POST .../api/general/debug`

خواندن یا تغییر تنظیمات دیباگ در `wp-config.php`: فعال/غیرفعال کردن دیباگ، ذخیرهٔ لاگ در فایل، نمایش لاگ روی صفحه.

- **بدون ورودی (یا `{}`):** فقط وضعیت فعلی برمی‌گردد.
- **با ورودی:** مقادیر در wp-config به‌روز می‌شوند.
  - `enabled`: true/false → WP_DEBUG
  - `log_to_file`: true/false → WP_DEBUG_LOG (ذخیره در فایل)
  - `display`: true/false → WP_DEBUG_DISPLAY (نمایش روی صفحه؛ معمولاً false تا لاگ فقط در فایل باشد)

**JSON ورودی – فقط خواندن وضعیت:**
```json
{}
```

**JSON ورودی – فعال کردن دیباگ و ذخیره لاگ در فایل (بدون نمایش):**
```json
{
  "enabled": true,
  "log_to_file": true,
  "display": false
}
```

**درخواست کامل (PowerShell):**
```powershell
# خواندن وضعیت
Invoke-RestMethod -Uri "$BASE/general/debug" -Method Post -ContentType "application/json" -Body '{}'

# تنظیم: دیباگ روشن، لاگ در فایل، بدون نمایش
Invoke-RestMethod -Uri "$BASE/general/debug" -Method Post -ContentType "application/json" -Body '{"enabled":true,"log_to_file":true,"display":false}'
```

**پاسخ نمونه:**
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

---

## 13. خواندن فایل لاگ (Log)

**URL:** `POST .../api/general/log`

خواندن محتوای فایل لاگ. پیش‌فرض: `wp-content/debug.log`. می‌توان با `path` مسیر دیگری (داخل روت وردپرس) داد.

**JSON ورودی – پیش‌فرض (debug.log):**
```json
{}
```

**JSON ورودی – مسیر دلخواه:**
```json
{
  "path": "wp-content/debug.log"
}
```

**درخواست کامل (PowerShell):**
```powershell
Invoke-RestMethod -Uri "$BASE/general/log" -Method Post -ContentType "application/json" -Body '{}'
```

**پاسخ نمونه:**
```json
{
  "success": true,
  "message": "Log file read.",
  "data": {
    "path": "C:\\...\\wp-content\\debug.log",
    "content": "محتوای فایل لاگ..."
  }
}
```

---

## 14. آدرس سایت و Home (Site URLs)

**URL:** `POST .../api/general/site-urls`

برگرداندن آدرس اصلی سایت و Home طبق جدول `options`: `siteurl` و `home`.

**JSON ورودی (نمونه):**
```json
{}
```

**درخواست کامل (PowerShell):**
```powershell
Invoke-RestMethod -Uri "$BASE/general/site-urls" -Method Post -ContentType "application/json" -Body '{}'
```

**پاسخ نمونه:**
```json
{
  "success": true,
  "message": "",
  "data": {
    "siteurl": "http://localhost/wpagentify/wordpress",
    "home": "http://localhost/wpagentify/wordpress"
  }
}
```

---

## 15. Query – خواندن (Read)

**URL:** `POST .../api/query/read`

اجرای فقط کوئری **SELECT**. برای خواندن از دیتابیس بدون تغییر داده.

**JSON ورودی – کوئری ساده:**
```json
{
  "sql": "SELECT * FROM wp_options WHERE option_name = 'siteurl' LIMIT 1"
}
```

**JSON ورودی – با bindings (جایگزین امن پارامترها):**
```json
{
  "sql": "SELECT option_value FROM wp_options WHERE option_name = %s LIMIT 1",
  "bindings": ["siteurl"]
}
```

**درخواست کامل (PowerShell):**
```powershell
Invoke-RestMethod -Uri "$BASE/query/read" -Method Post -ContentType "application/json" -Body '{"sql":"SELECT option_name, option_value FROM wp_options LIMIT 3"}'
```

**پاسخ نمونه:**
```json
{
  "success": true,
  "message": "Query executed.",
  "data": {
    "rows": [
      { "option_name": "siteurl", "option_value": "http://localhost/..." }
    ],
    "count": 1
  }
}
```

---

## 16. Query – اجرای نوشتن (Execute)

**URL:** `POST .../api/query/execute`

اجرای کوئری‌های **INSERT**, **UPDATE** یا **DELETE**. فقط این سه نوع مجاز است.

**JSON ورودی – INSERT:**
```json
{
  "sql": "INSERT INTO wp_options (option_name, option_value, autoload) VALUES (%s, %s, %s)",
  "bindings": ["my_test_option", "test_value", "no"]
}
```

**JSON ورودی – UPDATE:**
```json
{
  "sql": "UPDATE wp_options SET option_value = %s WHERE option_name = %s",
  "bindings": ["new_value", "my_test_option"]
}
```

**JSON ورودی – DELETE:**
```json
{
  "sql": "DELETE FROM wp_options WHERE option_name = %s",
  "bindings": ["my_test_option"]
}
```

**درخواست کامل (PowerShell):**
```powershell
# INSERT
Invoke-RestMethod -Uri "$BASE/query/execute" -Method Post -ContentType "application/json" -Body '{"sql":"INSERT INTO wp_options (option_name, option_value, autoload) VALUES (%s, %s, %s)","bindings":["my_test_option","test_value","no"]}'
```

**پاسخ نمونه:**
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

---

## 17. Query – ساخت جدول (Create Table)

**URL:** `POST .../api/query/create-table`

ساخت جدول جدید؛ یا با **SQL خام (CREATE TABLE)** یا با تعریف **table** و **columns**.

**راه اول – SQL خام:**
```json
{
  "sql": "CREATE TABLE wp_my_table ( id bigint(20) unsigned NOT NULL AUTO_INCREMENT, name varchar(255) NOT NULL, PRIMARY KEY (id) ) DEFAULT CHARSET=utf8mb4"
}
```

**راه دوم – تعریف ستون‌ها (نام جدول با یا بدون prefix وردپرس):**
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

**انواع type برای columns:** `id`, `int`/`integer`, `bigint`, `string`/`varchar`, `text`, `longtext`, `boolean`, `datetime`/`timestamp`, `date`, `decimal` (با `total`, `places`).

**درخواست کامل (PowerShell):**
```powershell
Invoke-RestMethod -Uri "$BASE/query/create-table" -Method Post -ContentType "application/json" -Body '{"table":"my_test_table","columns":[{"name":"id","type":"id"},{"name":"name","type":"string","length":100}]}'
```

**پاسخ نمونه:**
```json
{
  "success": true,
  "message": "Table created.",
  "data": {
    "table": "wp_my_test_table"
  }
}
```

---

## 18. Query – پشتیبان جدول (Backup)

**URL:** `POST .../api/query/backup`

گرفتن پشتیبان از یک جدول: ساختار (SHOW CREATE TABLE) و همهٔ ردیف‌ها.

**JSON ورودی:**
```json
{
  "table": "options"
}
```

نام جدول با یا بدون prefix وردپرس (مثلاً `options` یا `wp_options`).

**درخواست کامل (PowerShell):**
```powershell
Invoke-RestMethod -Uri "$BASE/query/backup" -Method Post -ContentType "application/json" -Body '{"table":"options"}'
```

**پاسخ نمونه:**
```json
{
  "success": true,
  "message": "Table backed up.",
  "data": {
    "table": "wp_options",
    "row_count": 150,
    "saved_to": "C:\\...\\wp-content\\plugitify-backups\\backup-wp_options-2026-02-12-1.json"
  }
}
```

- پشتیبان علاوه بر برگشت در پاسخ، **به‌صورت خودکار در فایل ذخیره می‌شود** در مسیر:
  - **`wp-content/plugitify-backups/`**
- نام فایل: **`backup-{نام_جدول}-{تاریخ}-{شماره}.json`**  
  مثال: `backup-wp_options-2026-02-12-1.json` (اولین بکاپ آن جدول در آن روز)، `...-2.json` (دومین)، و غیره.
- همان خروجی را می‌توانی با **query/restore** (با ارسال `backup` یا `create_sql` + `rows` یا **آدرس فایل** `file`) بازگردانی کنی.

---

## 18b. Query – لیست بکاپ‌ها (Backup List)

**URL:** `POST .../api/query/backup-list`

برگرداندن لیست فایل‌های بکاپ داخل `wp-content/plugitify-backups/` به‌همراه مسیر و متادیتا (جدول، تعداد ردیف، زمان بکاپ).

**JSON ورودی:**
```json
{}
```

**درخواست کامل (PowerShell):**
```powershell
Invoke-RestMethod -Uri "$BASE/query/backup-list" -Method Post -ContentType "application/json" -Body '{}'
```

**پاسخ نمونه:**
```json
{
  "success": true,
  "message": "Backups listed.",
  "data": {
    "backups": [
      {
        "path": "C:\\...\\wp-content\\plugitify-backups\\backup-wp_options-2026-02-12-1.json",
        "filename": "backup-wp_options-2026-02-12-1.json",
        "table": "wp_options",
        "row_count": 150,
        "backup_at": "2026-02-12 17:30:00"
      }
    ],
    "count": 1
  }
}
```

از فیلد **`path`** (یا **`filename`** در همان پوشه) می‌توانی در **query/restore** با پارامتر **`file`** استفاده کنی.

---

## 19. Query – بازگردانی جدول (Restore)

**URL:** `POST .../api/query/restore`

بازگردانی جدول از خروجی **backup** یا از **فایل بکاپ**. با `drop_first: true` (پیش‌فرض) اول جدول حذف می‌شود.

**راه اول – با آدرس فایل بکاپ (پیشنهادی):**
```json
{
  "file": "C:\\...\\wp-content\\plugitify-backups\\backup-wp_options-2026-02-12-1.json",
  "drop_first": true
}
```
یا فقط نام فایل (در پوشهٔ بکاپ):
```json
{
  "file": "backup-wp_options-2026-02-12-1.json",
  "drop_first": true
}
```

**راه دوم – ارسال مستقیم create_sql و rows:**
```json
{
  "create_sql": "CREATE TABLE `wp_my_backup` (...)",
  "rows": [
    { "id": 1, "name": "a" },
    { "id": 2, "name": "b" }
  ],
  "drop_first": true
}
```

**راه سوم – ارسال کل آبجکت backup (مثل خروجی query/backup):**
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

**درخواست کامل (PowerShell):**
```powershell
# بازگردانی از فایل (path از خروجی query/backup-list)
Invoke-RestMethod -Uri "$BASE/query/restore" -Method Post -ContentType "application/json" -Body '{"file":"C:\\path\\to\\wp-content\\plugitify-backups\\backup-wp_options-2026-02-12-1.json","drop_first":true}'

# یا با نام فایل
Invoke-RestMethod -Uri "$BASE/query/restore" -Method Post -ContentType "application/json" -Body '{"file":"backup-wp_options-2026-02-12-1.json"}'
```

**پاسخ نمونه:**
```json
{
  "success": true,
  "message": "Table restored.",
  "data": {
    "inserted_rows": 150
  }
}
```

---

## 20. Query – لیست جداول و ستون‌ها (Tables)

**URL:** `POST .../api/query/tables`

برگرداندن همهٔ جداول دیتابیس وردپرس فعلی به‌همراه ستون‌های هر جدول (نام، نوع، nullable، key، default، extra).

**JSON ورودی – همهٔ جداول:**
```json
{}
```

**JSON ورودی – فقط جداول با prefix وردپرس (مثلاً wp_):**
```json
{
  "prefix_only": true
}
```

**درخواست کامل (PowerShell):**
```powershell
Invoke-RestMethod -Uri "$BASE/query/tables" -Method Post -ContentType "application/json" -Body '{}'
```

**پاسخ نمونه:**
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
          { "name": "option_value", "type": "longtext", "nullable": false, "key": "", "default": null, "extra": "" },
          { "name": "autoload", "type": "varchar", "nullable": false, "key": "", "default": "yes", "extra": "" }
        ]
      }
    ],
    "count": 1
  }
}
```

---

## خلاصهٔ endpointها (همه POST + JSON)

| endpoint | پارامترهای JSON |
|----------|------------------|
| `file/wp-path` | `{}` (بدنه خالی) |
| `file/list-directory` | `{"path": "مسیر پوشه"}` |
| `file/read` | `{"path": "مسیر فایل"}` |
| `file/grep` | `{"path": "پوشه", "pattern": "متن یا regex", "regex": true/false, "case_sensitive": true/false}` |
| `file/create-folder` | `{"path": "مسیر پوشه جدید"}` |
| `file/create` | `{"path": "مسیر فایل جدید"}` |
| `file/replace-content` | `{"path": "مسیر فایل", "content": "متن جدید کل فایل"}` |
| `file/replace-line` | `{"path": "مسیر فایل", "line_number": 1, "content": "متن خط جدید"}` |
| `file/delete` | `{"path": "مسیر فایل یا پوشه"}` (فایل = حذف فایل، پوشه = حذف بازگشتی) |
| **General** | |
| `general/plugins` | `{}` → لیست پلاگین‌ها با جزئیات و وضعیت فعال |
| `general/themes` | `{}` → لیست تم‌ها با جزئیات و تم فعال |
| `general/debug` | `{}` = خواندن وضعیت؛ `{"enabled", "log_to_file", "display"}` = تغییر در wp-config |
| `general/log` | `{}` = خواندن wp-content/debug.log؛ `{"path": "..."}` = مسیر دلخواه |
| `general/site-urls` | `{}` → siteurl و home از جدول options |
| **Query** | |
| `query/read` | `{"sql": "SELECT ...", "bindings": []}` فقط SELECT |
| `query/execute` | `{"sql": "INSERT/UPDATE/DELETE ...", "bindings": []}` |
| `query/create-table` | `{"table": "نام", "columns": [...]}` یا `{"sql": "CREATE TABLE ..."}` |
| `query/backup` | `{"table": "نام جدول"}` → ذخیره در فایل؛ خروجی: table, row_count, saved_to |
| `query/backup-list` | `{}` → لیست فایل‌های بکاپ با path و متادیتا |
| `query/restore` | `{"file": "مسیر یا نام فایل بکاپ"}` یا `{"create_sql": "...", "rows": [...]}` یا `{"backup": {...}}` |
| `query/tables` | `{}` یا `{"prefix_only": true}` → لیست جداول + ستون‌های هر جدول |

---

## ترتیب پیشنهادی برای تست

1. `POST file/wp-path` با بدنه `{}`
2. `POST file/list-directory` با `{"path":"wp-content/plugins/plugitify"}`
3. `POST file/read` با `{"path":"wp-content/plugins/plugitify/plugifity.php"}`
4. `POST file/grep` با `{"path":"wp-content/plugins/plugitify/src","pattern":"Response"}`
5. `POST file/create-folder` با `{"path":"wp-content/plugins/plugitify/test-folder"}`
6. `POST file/create` با `{"path":"wp-content/plugins/plugitify/test-folder/empty.txt"}`
7. `POST file/replace-content` با `{"path":"...","content":"Hello\nWorld"}`
8. `POST file/read` دوباره برای همان فایل
9. `POST file/replace-line` با `{"path":"...","line_number":1,"content":"First line"}`
10. `POST file/delete` با `{"path":"wp-content/plugins/plugitify/test-folder/empty.txt"}` (حذف فایل)، بعد با `{"path":"wp-content/plugins/plugitify/test-folder"}` (حذف پوشه)

یا برای پاک کردن پوشهٔ تست فقط: `POST file/delete` با `{"path":"wp-content/plugins/plugitify/test-folder"}` (پوشه و محتویاتش حذف می‌شود).

**Query (دیتابیس):**
11. `POST query/read` با `{"sql":"SELECT option_name, option_value FROM wp_options LIMIT 3"}`
12. `POST query/create-table` با `{"table":"my_test_table","columns":[{"name":"id","type":"id"},{"name":"name","type":"string","length":100}]}`
13. `POST query/execute` با `{"sql":"INSERT INTO wp_my_test_table (name) VALUES (%s)","bindings":["test row"]}`
14. `POST query/read` با `{"sql":"SELECT * FROM wp_my_test_table"}`
15. `POST query/backup` با `{"table":"my_test_table"}` → خروجی را ذخیره کن
16. `POST query/restore` با `{"backup": <خروجی backup>,"drop_first":true}` برای تست بازگردانی (اختیاری)
