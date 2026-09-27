<?php
/**
 * BookStack MCP Server — Help & Meta Tools
 *
 * @package    BookstackMCP\Tools
 * @author     Daniel Morante
 * @copyright  2026 The Daniel Morante Company, Inc.
 * @license    BSD-2-Clause
 */

use EnchiladaMCP\McpTool;
use EnchiladaMCP\ToolResult;
use BookStack\InstanceManager;

class HelpTools
{
	private InstanceManager $manager;

	public function __construct(InstanceManager $manager)
	{
		$this->manager = $manager;
	}

	#[McpTool(
		name: 'bookstack_server_info',
		description: 'Server version, configured instances and tool categories.',
		readOnlyHint: true,
		inputSchema: [
			'type' => 'object',
			'properties' => new \stdClass(),
		]
	)]
	public function bookstack_server_info(): ToolResult
	{
		$info = [
			'name' => APPLICATION_NAME,
			'version' => APPLICATION_VERSION,
			'instances' => $this->manager->listInstances(),
			'tool_count' => 57,
			'categories' => [
				'content' => 'books, pages, chapters, shelves — CRUD + export',
				'media' => 'attachments, images — manage file assets',
				'search' => 'full-text search across all content types',
				'admin' => 'users, roles, permissions, audit log, recycle bin',
				'system' => 'instance info, configured instances',
			],
			'api_docs' => 'https://demo.bookstackapp.com/api/docs',
			'response_format' => 'Markdown text with a structuredContent JSON payload (MCP 2025-06-18 dual content)',
		];

		$lines = [
			'# ' . $info['name'] . ' [v' . $info['version'] . ']',
			'',
			'Tools: ' . $info['tool_count'],
			'',
			'## Instances',
		];
		foreach ($info['instances'] as $name => $config) {
			$lines[] = '- **' . $name . '** — ' . ($config['url'] ?? '?')
				. (!empty($config['description']) ? ' (' . $config['description'] . ')' : '');
		}
		$lines[] = '';
		$lines[] = '## Tool categories';
		foreach ($info['categories'] as $category => $description) {
			$lines[] = '- **' . $category . '**: ' . $description;
		}
		$lines[] = '';
		$lines[] = 'API docs: ' . $info['api_docs'];

		return ToolResult::structured(implode("\n", $lines), $info);
	}

	#[McpTool(
		name: 'bookstack_help',
		description: 'Short how-to guides for these tools.',
		readOnlyHint: true,
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'topic' => ['type' => 'string', 'description' => 'getting_started (default), content_creation, search, user_management, multi_instance, best_practices'],
			],
		]
	)]
	public function bookstack_help(string $topic = 'getting_started'): ToolResult
	{
		$topics = [
			'getting_started' => [
				'title' => 'Getting Started',
				'steps' => [
					'1. bookstack_list_instances: pick an instance (required on every call)',
					'2. bookstack_books_list: browse books',
					'3. bookstack_books_read: a book\'s chapters and pages',
					'4. bookstack_pages_read: full page content',
					'5. bookstack_search: find content of any type',
				],
			],
			'content_creation' => [
				'title' => 'Creating Content',
				'steps' => [
					'1. bookstack_books_create: the container',
					'2. bookstack_chapters_create: optional grouping',
					'3. bookstack_pages_create: content (Markdown preferred)',
					'4. bookstack_attachments_create: link external URLs to a page',
					'5. bookstack_images_create: upload base64 images',
				],
				'tip' => 'Write pages in Markdown; it is more token-efficient than HTML.',
			],
			'search' => [
				'title' => 'Searching Content',
				'syntax' => [
					'"exact phrase"',
					'{type:page} — page, book, chapter or shelf',
					'{tag:name=value}',
					'{created_by:me}',
				],
				'tip' => 'Results are snippets; read full pages with bookstack_pages_read.',
			],
			'user_management' => [
				'title' => 'User Management',
				'steps' => [
					'bookstack_users_list / bookstack_roles_list: current users and roles',
					'bookstack_users_create: pass role IDs',
					'bookstack_users_delete: pass migrate_ownership_id to keep their content',
				],
			],
			'multi_instance' => [
				'title' => 'Multi-Instance Management',
				'steps' => [
					'bookstack_list_instances: configured instances',
				],
				'tip' => 'Pass instance on every call; there is no default instance.',
			],
			'best_practices' => [
				'title' => 'Best Practices',
				'tips' => [
					'Write Markdown, not HTML (fewer tokens)',
					'Read a page before updating it: updates replace the whole content',
					'bookstack_books_export gets a whole book in one call',
					'Check the recycle bin before reporting content as missing',
				],
			],
		];

		$result = $topics[$topic] ?? ['error' => "Unknown topic: {$topic}. Available: " . implode(', ', array_keys($topics))];

		if (isset($result['error'])) {
			return ToolResult::structured("# Error\n\n" . $result['error'], $result);
		}

		$lines = ['# ' . ($result['title'] ?? $topic), ''];
		foreach (['steps', 'syntax', 'tips'] as $key) {
			foreach ($result[$key] ?? [] as $item) {
				$lines[] = '- ' . $item;
			}
		}
		if (!empty($result['tip'])) {
			$lines[] = '';
			$lines[] = '**Tip:** ' . $result['tip'];
		}

		return ToolResult::structured(implode("\n", $lines), $result);
	}

	#[McpTool(
		name: 'bookstack_error_guide',
		description: 'Causes and fixes for BookStack API errors.',
		readOnlyHint: true,
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'error_code' => ['type' => 'string', 'description' => 'UNAUTHORIZED, NOT_FOUND, VALIDATION_ERROR, FORBIDDEN; omit to list'],
			],
		]
	)]
	public function bookstack_error_guide(string $error_code = ''): ToolResult
	{
		$errors = [
			'UNAUTHORIZED' => [
				'message' => 'Authentication failed or token invalid',
				'causes' => ['Invalid API token ID or secret', 'Token expired or revoked', 'Missing Authorization header'],
				'solutions' => ['Verify token_id and token_secret in instances.json', 'Create a new API token in BookStack user settings', 'Check the instance URL is correct'],
			],
			'NOT_FOUND' => [
				'message' => 'Requested resource does not exist',
				'causes' => ['Invalid ID', 'Resource was deleted', 'Insufficient permissions to view'],
				'solutions' => ['Verify the resource ID', 'Check the recycle bin with bookstack_recyclebin_list', 'Confirm API token has required permissions'],
			],
			'VALIDATION_ERROR' => [
				'message' => 'Request parameters failed validation',
				'causes' => ['Required fields missing (e.g., name)', 'Invalid data format', 'Content too long'],
				'solutions' => ['Check required parameters in tool description', 'Ensure IDs are integers, not strings', 'Reduce content size'],
			],
			'FORBIDDEN' => [
				'message' => 'Access denied — insufficient permissions',
				'causes' => ['API token lacks required role permissions', 'Content-level permissions restrict access'],
				'solutions' => ['Check API token\'s role has required permissions', 'Use bookstack_permissions_read to check content permissions'],
			],
		];

		if (empty($error_code)) {
			$result = ['available_codes' => array_keys($errors)];
			return ToolResult::structured(
				"# Error Guide\n\nAvailable codes: " . implode(', ', array_keys($errors)),
				$result
			);
		}

		$key = strtoupper($error_code);
		$result = $errors[$key] ?? ['error' => "Unknown error code: {$error_code}. Available: " . implode(', ', array_keys($errors))];

		if (isset($result['error'])) {
			return ToolResult::structured("# Error\n\n" . $result['error'], $result);
		}

		$lines = ['# ' . $key, ''];
		$lines[] = $result['message'] ?? '';
		$lines[] = '';
		$lines[] = '## Causes';
		foreach ($result['causes'] ?? [] as $cause) {
			$lines[] = '- ' . $cause;
		}
		$lines[] = '';
		$lines[] = '## Solutions';
		foreach ($result['solutions'] ?? [] as $solution) {
			$lines[] = '- ' . $solution;
		}

		return ToolResult::structured(implode("\n", $lines), $result);
	}

	#[McpTool(
		name: 'bookstack_tool_categories',
		description: 'List tool categories, or the tools in one category.',
		readOnlyHint: true,
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'category' => ['type' => 'string', 'description' => 'books, pages, chapters, shelves, attachments, images, users, roles, search, system'],
			],
		]
	)]
	public function bookstack_tool_categories(string $category = ''): ToolResult
	{
		$categories = [
			'books' => [
				'description' => 'Manage books — the top-level containers for documentation',
				'tools' => ['bookstack_books_list', 'bookstack_books_read', 'bookstack_books_create', 'bookstack_books_update', 'bookstack_books_delete', 'bookstack_books_export'],
			],
			'pages' => [
				'description' => 'Manage individual pages — the core content units',
				'tools' => ['bookstack_pages_list', 'bookstack_pages_read', 'bookstack_pages_create', 'bookstack_pages_update', 'bookstack_pages_delete', 'bookstack_pages_export'],
			],
			'chapters' => [
				'description' => 'Manage chapters — organize pages within books',
				'tools' => ['bookstack_chapters_list', 'bookstack_chapters_read', 'bookstack_chapters_create', 'bookstack_chapters_update', 'bookstack_chapters_delete', 'bookstack_chapters_export'],
			],
			'shelves' => [
				'description' => 'Manage shelves — organize multiple books into collections',
				'tools' => ['bookstack_shelves_list', 'bookstack_shelves_read', 'bookstack_shelves_create', 'bookstack_shelves_update', 'bookstack_shelves_delete'],
			],
			'attachments' => [
				'description' => 'Manage file attachments linked to pages',
				'tools' => ['bookstack_attachments_list', 'bookstack_attachments_read', 'bookstack_attachments_create', 'bookstack_attachments_update', 'bookstack_attachments_delete'],
			],
			'images' => [
				'description' => 'Manage the image gallery',
				'tools' => ['bookstack_images_list', 'bookstack_images_read', 'bookstack_images_create', 'bookstack_images_update', 'bookstack_images_delete'],
			],
			'users' => [
				'description' => 'Manage user accounts',
				'tools' => ['bookstack_users_list', 'bookstack_users_read', 'bookstack_users_create', 'bookstack_users_update', 'bookstack_users_delete'],
			],
			'roles' => [
				'description' => 'Manage user roles and permissions',
				'tools' => ['bookstack_roles_list', 'bookstack_roles_read', 'bookstack_roles_create', 'bookstack_roles_update', 'bookstack_roles_delete'],
			],
			'search' => [
				'description' => 'Search across all content types',
				'tools' => ['bookstack_search'],
			],
			'system' => [
				'description' => 'System info, audit log, permissions, recycle bin, instance management',
				'tools' => ['bookstack_system_info', 'bookstack_audit_log', 'bookstack_permissions_read', 'bookstack_permissions_update', 'bookstack_recyclebin_list', 'bookstack_recyclebin_restore', 'bookstack_recyclebin_destroy', 'bookstack_list_instances'],
			],
		];

		if (empty($category)) {
			$summary = [];
			$lines = ['# Tool Categories', ''];
			foreach ($categories as $name => $info) {
				$summary[$name] = $info['description'] . ' (' . count($info['tools']) . ' tools)';
				$lines[] = '- **' . $name . '**: ' . $summary[$name];
			}
			return ToolResult::structured(implode("\n", $lines), $summary);
		}

		$result = $categories[$category] ?? ['error' => "Unknown category: {$category}. Available: " . implode(', ', array_keys($categories))];

		if (isset($result['error'])) {
			return ToolResult::structured("# Error\n\n" . $result['error'], $result);
		}

		$lines = ['# Category: ' . $category, '', $result['description'] ?? '', ''];
		foreach ($result['tools'] ?? [] as $tool) {
			$lines[] = '- ' . $tool;
		}

		return ToolResult::structured(implode("\n", $lines), $result);
	}

	#[McpTool(
		name: 'bookstack_usage_examples',
		description: 'Tool sequences for common workflows.',
		readOnlyHint: true,
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'workflow' => ['type' => 'string', 'description' => 'create_documentation, organize_content, user_management, search_content, export_data; omit to list'],
			],
		]
	)]
	public function bookstack_usage_examples(string $workflow = ''): ToolResult
	{
		$workflows = [
			'create_documentation' => [
				'title' => 'Create Complete Documentation Project',
				'steps' => [
					['tool' => 'bookstack_books_create', 'action' => 'Create a book as the container'],
					['tool' => 'bookstack_chapters_create', 'action' => 'Create chapters for organization'],
					['tool' => 'bookstack_pages_create', 'action' => 'Add pages with Markdown content'],
					['tool' => 'bookstack_shelves_create', 'action' => 'Optionally add book to a shelf'],
				],
			],
			'organize_content' => [
				'title' => 'Organize Existing Content',
				'steps' => [
					['tool' => 'bookstack_books_list', 'action' => 'List existing books'],
					['tool' => 'bookstack_shelves_create', 'action' => 'Create shelves for grouping'],
					['tool' => 'bookstack_shelves_update', 'action' => 'Assign books to shelves'],
					['tool' => 'bookstack_chapters_update', 'action' => 'Move chapters between books'],
				],
			],
			'user_management' => [
				'title' => 'Set Up Team Access',
				'steps' => [
					['tool' => 'bookstack_roles_list', 'action' => 'Review available roles'],
					['tool' => 'bookstack_users_create', 'action' => 'Create user accounts with roles'],
					['tool' => 'bookstack_permissions_update', 'action' => 'Set content-level permissions'],
				],
			],
			'search_content' => [
				'title' => 'Find and Read Content',
				'steps' => [
					['tool' => 'bookstack_search', 'action' => 'Search with query + advanced syntax'],
					['tool' => 'bookstack_pages_read', 'action' => 'Read full page content from results'],
					['tool' => 'bookstack_pages_export', 'action' => 'Export as Markdown for processing'],
				],
			],
			'export_data' => [
				'title' => 'Export Documentation',
				'steps' => [
					['tool' => 'bookstack_books_export', 'action' => 'Export entire book (markdown or html)'],
					['tool' => 'bookstack_chapters_export', 'action' => 'Export a single chapter'],
					['tool' => 'bookstack_pages_export', 'action' => 'Export individual pages'],
				],
				'tip' => 'markdown or plaintext exports cost fewer tokens than html.',
			],
		];

		if (empty($workflow)) {
			$summary = [];
			$lines = ['# Usage Examples', ''];
			foreach ($workflows as $name => $info) {
				$summary[$name] = $info['title'];
				$lines[] = '- **' . $name . '**: ' . $info['title'];
			}
			return ToolResult::structured(implode("\n", $lines), $summary);
		}

		$result = $workflows[$workflow] ?? ['error' => "Unknown workflow: {$workflow}. Available: " . implode(', ', array_keys($workflows))];

		if (isset($result['error'])) {
			return ToolResult::structured("# Error\n\n" . $result['error'], $result);
		}

		$lines = ['# ' . ($result['title'] ?? $workflow), ''];
		foreach ($result['steps'] ?? [] as $step) {
			$lines[] = '- **' . $step['tool'] . '** — ' . $step['action'];
		}
		if (!empty($result['tip'])) {
			$lines[] = '';
			$lines[] = '**Tip:** ' . $result['tip'];
		}

		return ToolResult::structured(implode("\n", $lines), $result);
	}
}
