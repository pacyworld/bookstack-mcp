<?php
/**
 * BookStack MCP Server — System & Admin Tools
 *
 * @package    BookstackMCP\Tools
 * @author     Daniel Morante
 * @copyright  2026 The Daniel Morante Company, Inc.
 * @license    BSD-2-Clause
 */

use EnchiladaMCP\McpTool;
use BookStack\InstanceManager;

class SystemTools
{
	private InstanceManager $manager;

	public function __construct(InstanceManager $manager)
	{
		$this->manager = $manager;
	}

	#[McpTool(
		name: 'bookstack_system_info',
		description: 'Get BookStack instance information including version, base URL, and app name.',
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'instance' => ['type' => 'string', 'description' => 'BookStack instance name (optional)'],
			],
		]
	)]
	public function bookstack_system_info(string $instance = ''): array
	{
		$client = $this->manager->getClient($instance ?: null);
		return $client->get('');
	}

	#[McpTool(
		name: 'bookstack_audit_log',
		description: 'Retrieve the audit log to see recent activities on the instance.',
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'count' => ['type' => 'integer', 'description' => 'Number of entries to return (default 20, max 500)'],
				'offset' => ['type' => 'integer', 'description' => 'Pagination offset'],
				'instance' => ['type' => 'string', 'description' => 'BookStack instance name (optional)'],
			],
		]
	)]
	public function bookstack_audit_log(int $count = 20, int $offset = 0, string $instance = ''): array
	{
		$client = $this->manager->getClient($instance ?: null);
		return $client->get('audit-log', ['count' => min($count, 500), 'offset' => $offset]);
	}

	#[McpTool(
		name: 'bookstack_permissions_read',
		description: 'Check permissions for a specific content item (book, chapter, page, or shelf).',
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'content_type' => ['type' => 'string', 'description' => 'Entity type: book, chapter, page, bookshelf'],
				'content_id' => ['type' => 'integer', 'description' => 'ID of the entity'],
				'instance' => ['type' => 'string', 'description' => 'BookStack instance name (optional)'],
			],
			'required' => ['content_type', 'content_id'],
		]
	)]
	public function bookstack_permissions_read(string $content_type, int $content_id, string $instance = ''): array
	{
		$client = $this->manager->getClient($instance ?: null);
		$type = rtrim($content_type, 's') . 's';
		return $client->get("content-permissions/{$content_type}/{$content_id}");
	}

	#[McpTool(
		name: 'bookstack_permissions_update',
		description: 'Set custom permissions for a specific content item. Overrides default role-based access.',
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'content_type' => ['type' => 'string', 'description' => 'Entity type: book, chapter, page, bookshelf'],
				'content_id' => ['type' => 'integer', 'description' => 'ID of the entity'],
				'owner_id' => ['type' => 'integer', 'description' => 'New owner user ID (optional)'],
				'instance' => ['type' => 'string', 'description' => 'BookStack instance name (optional)'],
			],
			'required' => ['content_type', 'content_id'],
		]
	)]
	public function bookstack_permissions_update(string $content_type, int $content_id, int $owner_id = 0, string $instance = ''): array
	{
		$client = $this->manager->getClient($instance ?: null);
		$data = [];
		if ($owner_id > 0) $data['owner_id'] = $owner_id;
		return $client->put("content-permissions/{$content_type}/{$content_id}", $data);
	}
}
