=== Plugitify ===

Contributors: amirsafari
Tags: ai, assistant, wordpress, chatbot, automation, deepseek, openai, claude, gemini
Requires at least: 5.0
Tested up to: 6.9
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Plugitify is an AI-powered WordPress assistant that helps administrators manage and operate their WordPress site through a conversational chat interface—with support for database queries, plugins list, file operations, tasks, and file exports (ZIP, TXT, Excel, PDF).

== Description ==

**Plugitify** (Agentify in the admin menu) is a professional WordPress assistant powered by AI. It is designed for users with little or no technical experience: you ask questions in natural language, and the assistant guides you step-by-step, runs safe operations on your site, and presents results in clear tables and actions.

= What is Plugitify? =

* **AI assistant in WordPress admin** — A chat panel inside the WordPress dashboard (menu: **Agentify**) where you talk to an AI that understands WordPress.
* **Multi-provider support** — Works with **DeepSeek**, **OpenAI (ChatGPT)**, **Google Gemini**, and **Anthropic (Claude)**. You choose the model and add your API key in the Assistant settings.
* **Tool-powered** — The assistant does not only answer in text; it can run read-only database queries, list plugins, read/list files, create tasks, show tables and buttons, and (if you allow it) execute database changes or generate and upload ZIP, TXT, Excel, and PDF files to the media library.
* **Safe by default** — Destructive or sensitive database operations require explicit admin confirmation. Read-only actions (e.g. listing plugins, SELECT queries) do not.

= How does it work? =

1. **Install & activate** the plugin, then open **Agentify** in the admin menu.
2. Go to **Assistant settings** and set:
   * **Model** (e.g. DeepSeek, ChatGPT, Gemini, Claude)
   * **API key** for the chosen provider
   * Optionally enable **Allow database changes** if you want the assistant to run INSERT/UPDATE/DELETE (with your confirmation when needed).
3. **Start a chat** — type your question or request (e.g. “List my plugins”, “Show WordPress users”, “Remember to check backups tomorrow”).
4. The AI uses its **tools** (database, plugins, files, tasks, etc.) and responds with text, tables, and actions. You can create multiple chats; each keeps its own history and tasks.

= What can Plugitify do? =

* **Database**
  * Run **read-only** SQL (SELECT) to inspect users, options, posts, and other WordPress data.
  * Run **write** SQL (INSERT, UPDATE, DELETE, REPLACE) when “Allow database changes” is on and you confirm when asked.
* **Plugins**
  * List installed plugins and their status (active/inactive).
* **Files**
  * List directories and read text files inside the WordPress installation.
  * Create and upload to the media library: **ZIP**, **TXT**, **Excel (XLSX)**, and **PDF**.
* **Tasks**
  * Create and manage tasks/todos per chat (e.g. “Remember to …”), list them, and mark them completed.
* **Chat**
  * Set chat titles, keep conversation history per chat, and get answers in your language (e.g. Persian or English).
* **Presentation**
  * Show data in **tables**, **multiple-choice questions**, and **link buttons**; build site URLs (front-end or admin) for you.

= Available tools (for the AI) =

The assistant uses these tools when answering you:

| Tool | Description |
|------|-------------|
| **set_chat_title** | Set a short title for the current conversation. |
| **query_database** | Run read-only SQL SELECT queries on the WordPress database. |
| **execute_database** | Run write SQL (INSERT, UPDATE, DELETE, REPLACE); requires admin approval and “Allow database changes” enabled. |
| **get_plugins** | Get the list of installed WordPress plugins and their status. |
| **list_directory** | List files and folders in WordPress directories. |
| **read_file** | Read the contents of text files in the WordPress installation. |
| **create_task** | Create a task/todo for the current chat. |
| **get_tasks** | Get the list of tasks for the current chat. |
| **complete_task** | Mark a task as completed. |
| **render_data_table** | Build and display HTML tables from data. |
| **multiple_choice_question** | Show a 4-option multiple choice question. |
| **render_button** | Create link buttons in the chat. |
| **build_site_link** | Build full URLs for the WordPress site (front or admin). |
| **create_zip_and_upload** | Create ZIP files and upload them to the media library. |
| **create_txt_and_upload** | Create text files and upload them to the media library. |
| **create_excel_and_upload** | Create Excel (XLSX) files and upload them to the media library. |
| **create_pdf_and_upload** | Create PDF files and upload them to the media library. |

= What Plugitify does NOT do (current version) =

* It does **not** write or generate plugin/theme code (PHP, JavaScript, CSS).
* It does **not** create or edit WordPress themes.
* It does **not** build or edit pages with page builders (e.g. Elementor).
* It does **not** generate images.

For such features, the assistant will tell you they are not available and may suggest alternatives where possible.

= Requirements =

* WordPress 5.0 or higher.
* PHP 7.4 or higher.
* An API key from at least one provider: **DeepSeek**, **OpenAI**, **Google Gemini**, or **Anthropic**.
* Administrator capability to access the Agentify menu and settings.

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/plugitify/` or install via WordPress admin (Plugins → Add New → Upload).
2. Activate the plugin through the **Plugins** screen.
3. Open **Agentify** in the admin menu.
4. In **Assistant settings**, choose the **model** (e.g. DeepSeek), enter the **API key**, and save.
5. Optionally enable **Allow database changes** if you want the assistant to run write queries (with your confirmation when needed).
6. Start a new chat and type your first question or request.

== Frequently Asked Questions ==

= Do I need an API key? =

Yes. Plugitify uses an external AI API (DeepSeek, OpenAI, Gemini, or Claude). You must enter the corresponding API key in Assistant settings. Keys are stored in your WordPress options and sent only to the provider you selected.

= Is it safe to allow database changes? =

Write operations (INSERT, UPDATE, DELETE, REPLACE) are only available when “Allow database changes” is enabled. For sensitive or destructive actions, the assistant is instructed to ask for your explicit confirmation before running them. You can leave this option off and use only read-only queries and other tools if you prefer.

= Can I use it in my language? =

Yes. The assistant is instructed to follow the user’s language (e.g. Persian or English). Ask your questions in your preferred language.

= Where are chats and tasks stored? =

Conversations and messages are stored in your WordPress database (custom tables). Tasks created by the assistant are also stored in the database and linked to the chat.

== Screenshots ==

1. Agentify chat panel in the WordPress admin.
2. Assistant settings: model selection and API keys.
3. Example: list of plugins in a table.
4. Example: tasks under a message.

== Changelog ==

= 1.0.0 =
* Initial release.
* AI assistant with DeepSeek, OpenAI, Gemini, and Claude.
* Tools: database (read/write with confirmation), plugins, directory listing, read file, tasks, data tables, buttons, multiple choice, site links.
* File export and upload: ZIP, TXT, Excel (XLSX), PDF to media library.
* Multiple chats with history and per-chat tasks.

== Upgrade Notice ==

= 1.0.0 =
First stable release of Plugitify (Agentify) — AI-powered WordPress assistant with multi-provider support and built-in tools.
