import { z } from 'zod';
import { callTool } from './api';
import type { AgentConfig } from './config';
import { buildBrowserTools } from './browserTools';
import type { Preview } from './preview';
import { defineTool } from './toolKit';

/** Wraps one server-side tool: the arguments go straight through to PHP. */
function backendTool(
  config: AgentConfig,
  name: string,
  description: string,
  parameters: z.ZodObject<z.ZodRawShape>,
) {
  return defineTool(name, description, parameters, (args) => callTool(config, name, args));
}

/**
 * The agent's full toolset: the filesystem and WordPress tools that run on the
 * server, plus the preview-pane tools that run here in the browser.
 *
 * Descriptions are written for the model, not for us: they state when to reach
 * for the tool and what the common mistake is.
 */
export function buildTools(config: AgentConfig, preview: Preview) {
  return [
    ...buildBrowserTools(preview),

    backendTool(
      config,
      'workspace_info',
      'Get your bearings: the plugin slug, its main file and header data, whether it is currently '
        + 'active, the WordPress and PHP versions, debug settings, and a file tree. Call this first '
        + 'in a new conversation before doing anything else.',
      z.object({}),
    ),

    backendTool(
      config,
      'site_extensions',
      'List every installed WordPress plugin and theme on the site. The result includes each item\'s '
        + 'active/inactive status, name, version, description, author, URI, requirements, and file or '
        + 'stylesheet details. This is read-only and covers the whole site, not only the current plugin '
        + 'workspace. Must-use plugins are included as always active.',
      z.object({}),
    ),

    backendTool(
      config,
      'list_files',
      'List the plugin\'s files and directories as a tree. Use it to explore structure; use read_file '
        + 'to see contents.',
      z.object({
        path: z.string().default('').describe('Directory relative to the plugin root. Empty string means the plugin root itself.'),
        max_depth: z.number().int().default(4).describe('How many directory levels deep to descend. 1-10.'),
      }),
    ),

    backendTool(
      config,
      'read_file',
      'Read a text file from the plugin, returned as numbered lines. ALWAYS read a file before you '
        + 'edit it — edit_file matches on exact text and will fail if you guess at the contents. '
        + 'Large files come back in pages; use offset to continue.',
      z.object({
        path: z.string().describe('File path relative to the plugin root, e.g. "includes/class-admin.php".'),
        offset: z.number().int().default(1).describe('First line to return, 1-based.'),
        limit: z.number().int().default(800).describe('How many lines to return.'),
      }),
    ),

    backendTool(
      config,
      'search_files',
      'Search the plugin\'s file contents with a regular expression, returning "path:line: text" for '
        + 'each hit. This is how you find where a function, hook, or class is defined without reading '
        + 'every file.',
      z.object({
        pattern: z.string().describe('PCRE regular expression, without delimiters. Example: "function\\\\s+my_prefix_".'),
        glob: z.string().default('').describe('Optional filename filter, e.g. "*.php". Empty means all text files.'),
        case_insensitive: z.boolean().default(false).describe('Match case-insensitively.'),
        max_results: z.number().int().default(100).describe('Maximum number of matching lines to return.'),
      }),
    ),

    backendTool(
      config,
      'glob_files',
      'Find files by name pattern, e.g. "*.php" or "assets/**". Use when you know roughly what a file '
        + 'is called but not where it lives.',
      z.object({
        pattern: z.string().describe('Glob pattern matched against both the relative path and the bare filename.'),
      }),
    ),

    backendTool(
      config,
      'write_file',
      'Create a new file, or completely replace an existing one. Parent directories are created '
        + 'automatically. Use edit_file instead when changing part of a file that already exists — '
        + 'rewriting a whole file to change three lines loses work and risks dropping code. After the '
        + 'file is saved, supported PHP, HTML, CSS, and JavaScript files are syntax-checked automatically. '
        + 'The result is informational only: syntax errors do not prevent the save.',
      z.object({
        path: z.string().describe('File path relative to the plugin root.'),
        content: z.string().describe('The complete file contents. Not a diff, not a fragment.'),
      }),
    ),

    backendTool(
      config,
      'edit_file',
      'Replace a code snippet in a file. Read the file first and copy old_string from its current '
        + 'contents, including a few surrounding lines so the match is unique. The tool handles '
        + 'LF/CRLF differences and harmless indentation-only differences, but refuses to guess when '
        + 'the match is missing or ambiguous. Retrying an already-completed edit is safe. After the '
        + 'edit is saved, supported PHP, HTML, CSS, and JavaScript files are syntax-checked automatically. '
        + 'The result is informational only: syntax errors do not prevent the edit.',
      z.object({
        path: z.string().describe('File path relative to the plugin root.'),
        old_string: z.string().describe('Text to replace, copied from the latest read_file result without line-number prefixes. Include surrounding context.'),
        new_string: z.string().describe('Text to put in its place. Use an empty string to delete the matched text.'),
        replace_all: z.boolean().default(false).describe('Replace every matching occurrence instead of failing when the snippet is repeated. Use only when every occurrence should change.'),
      }),
    ),

    backendTool(
      config,
      'delete_file',
      'Delete a single file from the plugin. There is no undo — only delete files you created or that '
        + 'the user asked you to remove.',
      z.object({ path: z.string().describe('File path relative to the plugin root.') }),
    ),

    backendTool(
      config,
      'create_directory',
      'Create a directory (and any missing parents) inside the plugin.',
      z.object({ path: z.string().describe('Directory path relative to the plugin root.') }),
    ),

    backendTool(
      config,
      'delete_directory',
      'Delete a directory inside the plugin. Fails on a non-empty directory unless recursive is true.',
      z.object({
        path: z.string().describe('Directory path relative to the plugin root.'),
        recursive: z.boolean().default(false).describe('Delete the directory and everything inside it.'),
      }),
    ),

    backendTool(
      config,
      'move_path',
      'Rename or move a file or directory within the plugin. Fails if the destination already exists.',
      z.object({
        from: z.string().describe('Current path, relative to the plugin root.'),
        to: z.string().describe('New path, relative to the plugin root.'),
      }),
    ),

    backendTool(
      config,
      'php_lint',
      'Run a PHP syntax check (php -l). Pass a path to check one file, or omit it to check every PHP '
        + 'file in the plugin. Run this after a batch of edits and before telling the user you are done '
        + '— a parse error in an active plugin takes the whole site down.',
      z.object({
        path: z.string().default('').describe('File to check, relative to the plugin root. Empty string checks all PHP files.'),
      }),
    ),

    backendTool(
      config,
      'plugin_control',
      'Check whether this plugin is active, or activate/deactivate it. Activation runs a syntax check '
        + 'first and refuses if the plugin would fatal. The plugin must be active for the user to see '
        + 'its effect in the preview pane.',
      z.object({
        action: z.enum(['status', 'activate', 'deactivate']).default('status').describe('What to do.'),
      }),
    ),

    backendTool(
      config,
      'debug_control',
      'Read or change WordPress debug logging (the WP_DEBUG / WP_DEBUG_LOG constants in wp-config.php). '
        + 'Enable it before trying to diagnose a fatal error or unexpected behaviour, then reload the '
        + 'preview and call read_debug_log.',
      z.object({
        action: z
          .enum(['status', 'enable', 'disable'])
          .default('status')
          .describe('"status" reports the current settings; "enable" turns on WP_DEBUG and WP_DEBUG_LOG.'),
      }),
    ),

    backendTool(
      config,
      'read_debug_log',
      'Read the tail of wp-content/debug.log, where PHP errors, warnings, and notices land. The most '
        + 'effective way to use this: clear the log, reload the preview with browser_navigate, then '
        + 'read it again so you see only the errors from that one request.',
      z.object({
        lines: z.number().int().default(200).describe('How many trailing lines to return.'),
        filter: z.string().default('').describe('Only return lines containing this substring, e.g. the plugin slug. Empty means no filter.'),
        clear: z.boolean().default(false).describe('Empty the log instead of reading it.'),
      }),
    ),
  ];
}
