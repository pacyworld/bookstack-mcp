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
		description: 'Get information about this BookStack MCP server including version, capabilities, and configured instances.',
		inputSchema: [
			'type' => 'object',
			'properties' => new \stdClass(),
		]
	)]
	public function bookstack_server_info(): array
	{
		return [
			'name' => APPLICATION_NAME,
			'version' => APPLICATION_VERSION,
			'instances' => $this->manager->listInstances(),
			'default_instance' => $this->manager->getDefault(),
			'tool_count' => 58,
			'categories' => [
				'content' => 'books, pages, chapters, shelves — CRUD + export',
				'media' => 'attachments, images — manage file assets',
				'search' => 'full-text search across all content types',
				'admin' => 'users, roles, permissions, audit log, recycle bin',
				'system' => 'instance info, instance switching',
			],
			'api_docs' => 'https://demo.bookstackapp.com/api/docs',
		];
	}

	#[McpTool(
		name: 'bookstack_help',
		description: 'Get context-aware help on how to use BookStack MCP tools.',
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'topic' => ['type' => 'string', 'description' => 'Topic: getting_started, content_creation, search, user_management, multi_instance, best_practices'],
			],
		]
	)]
	public function bookstack_help(string $topic = 'getting_started'): array
	{
		$topics = [
			'getting_started' => [
				'title' => 'Getting Started',
				'steps' => [
					'1. Use bookstack_list_instances to see configured BookStack instances',
					'2. Use bookstack_books_list to browse available books',
					'3. Use bookstack_books_read to explore a book\'s chapters and pages',
					'4. Use bookstack_pages_read to get full page content',
					'5. Use bookstack_search to find content across all types',
				],
			],
			'content_creation' => [
				'title' => 'Creating Content',
				'steps' => [
					'1. Create a book with bookstack_books_create',
					'2. Optionally create chapters with bookstack_chapters_create',
					'3. Create pages with bookstack_pages_create (prefer Markdown over HTML)',
					'4. Attach files with bookstack_attachments_create',
					'5. Upload images with bookstack_images_create (base64 encoded)',
				],
				'tip' => 'Always use Markdown format when creating/updating pages — it is more token-efficient than HTML.',
			],
			'search' => [
				'title' => 'Searching Content',
				'syntax' => [
					'"exact phrase" — search for exact text',
					'{type:page} — filter by content type (page, book, chapter, shelf)',
					'{tag:name=value} — filter by tag',
					'{created_by:me} — filter by creator',
				],
				'tip' => 'Search results contain snippets only. Use bookstack_pages_read with the page ID for full content.',
			],
			'user_management' => [
				'title' => 'User Management',
				'steps' => [
					'Use bookstack_users_list to see all users',
					'Use bookstack_roles_list to see available roles',
					'Create users with bookstack_users_create (assign roles)',
					'When deleting users, use migrate_ownership_id to transfer their content',
				],
			],
			'multi_instance' => [
				'title' => 'Multi-Instance Management',
				'steps' => [
					'Use bookstack_list_instances to see all configured instances',
					'Use bookstack_switch_instance to change the default',
					'Or pass instance parameter to any tool for one-off operations',
				],
				'tip' => 'The instance parameter is optional on all tools. Omit it to use the current default.',
			],
			'best_practices' => [
				'title' => 'Best Practices',
				'tips' => [
					'Use Markdown format for content — more token-efficient than HTML',
					'Use bookstack_pages_export with format=markdown to read content efficiently',
					'Always read a page before updating — updates replace content entirely',
					'Use bookstack_books_export to get all content from a book at once',
					'Check the recycle bin before reporting content as missing',
				],
			],
		];

		return $topics[$topic] ?? ['error' => "Unknown topic: {$topic}. Available: " . implode(', ', array_keys($topics))];
	}

	#[McpTool(
		name: 'bookstack_error_guide',
		description: 'Get information about common error codes and how to resolve them.',
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'error_code' => ['type' => 'string', 'description' => 'Error code or keyword: UNAUTHORIZED, NOT_FOUND, VALIDATION_ERROR, FORBIDDEN'],
			],
		]
	)]
	public function bookstack_error_guide(string $error_code = ''): array
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
			return ['available_codes' => array_keys($errors)];
		}

		$key = strtoupper($error_code);
		return $errors[$key] ?? ['error' => "Unknown error code: {$error_code}. Available: " . implode(', ', array_keys($errors))];
	}

	#[McpTool(
		name: 'bookstack_tool_categories',
		description: 'Get a list of tool categories and their descriptions.',
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'category' => ['type' => 'string', 'description' => 'Specific category: books, pages, chapters, shelves, attachments, images, users, roles, search, system, recyclebin'],
			],
		]
	)]
	public function bookstack_tool_categories(string $category = ''): array
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
				'tools' => ['bookstack_system_info', 'bookstack_audit_log', 'bookstack_permissions_read', 'bookstack_permissions_update', 'bookstack_recyclebin_list', 'bookstack_recyclebin_restore', 'bookstack_recyclebin_destroy', 'bookstack_list_instances', 'bookstack_switch_instance'],
			],
		];

		if (empty($category)) {
			$summary = [];
			foreach ($categories as $name => $info) {
				$summary[$name] = $info['description'] . ' (' . count($info['tools']) . ' tools)';
			}
			return $summary;
		}

		return $categories[$category] ?? ['error' => "Unknown category: {$category}. Available: " . implode(', ', array_keys($categories))];
	}

	#[McpTool(
		name: 'bookstack_usage_examples',
		description: 'Get common workflow examples for BookStack operations.',
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'workflow' => ['type' => 'string', 'description' => 'Workflow: create_documentation, organize_content, user_management, search_content, export_data'],
			],
		]
	)]
	public function bookstack_usage_examples(string $workflow = ''): array
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
				'tip' => 'Use "markdown" or "plaintext" format for LLM context injection — more token-efficient than HTML.',
			],
		];

		if (empty($workflow)) {
			$summary = [];
			foreach ($workflows as $name => $info) {
				$summary[$name] = $info['title'];
			}
			return $summary;
		}

		return $workflows[$workflow] ?? ['error' => "Unknown workflow: {$workflow}. Available: " . implode(', ', array_keys($workflows))];
	}
}
