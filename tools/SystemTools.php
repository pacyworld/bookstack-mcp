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
use EnchiladaMCP\ToolResult;
use BookStack\InstanceManager;
use BookStack\ResponseFormatter;

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
		readOnlyHint: true,
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'instance' => ['type' => 'string', 'description' => 'BookStack instance name'],
			],
			'required' => ['instance'],
		]
	)]
	public function bookstack_system_info(string $instance = ''): ToolResult
	{
		$client = $this->manager->getClient($instance);
		$response = $client->get('');
		return ToolResult::structured(ResponseFormatter::systemInfo(is_array($response) ? $response : ['raw' => $response]), is_array($response) ? $response : ['raw' => $response]);
	}

	#[McpTool(
		name: 'bookstack_audit_log',
		description: 'Retrieve the audit log to see recent activities on the instance.',
		readOnlyHint: true,
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'count' => ['type' => 'integer', 'description' => 'Number of entries to return (default 20, max 500)'],
				'offset' => ['type' => 'integer', 'description' => 'Pagination offset'],
				'instance' => ['type' => 'string', 'description' => 'BookStack instance name'],
			],
			'required' => ['instance'],
		]
	)]
	public function bookstack_audit_log(int $count = 20, int $offset = 0, string $instance = ''): ToolResult
	{
		$client = $this->manager->getClient($instance);
		$response = $client->get('audit-log', ['count' => min($count, 500), 'offset' => $offset]);
		return ToolResult::structured(ResponseFormatter::auditLog($response, $offset), $response);
	}

	#[McpTool(
		name: 'bookstack_permissions_read',
		description: 'Check permissions for a specific content item (book, chapter, page, or shelf).',
		readOnlyHint: true,
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'content_type' => ['type' => 'string', 'description' => 'Entity type: book, chapter, page, bookshelf'],
				'content_id' => ['type' => 'integer', 'description' => 'ID of the entity'],
				'instance' => ['type' => 'string', 'description' => 'BookStack instance name'],
			],
			'required' => ['content_type', 'content_id', 'instance'],
		]
	)]
	public function bookstack_permissions_read(string $content_type, int $content_id, string $instance = ''): ToolResult
	{
		$client = $this->manager->getClient($instance);
		$type = rtrim($content_type, 's') . 's';
		$response = $client->get("content-permissions/{$content_type}/{$content_id}");
		return ToolResult::structured(
			ResponseFormatter::permissionsDetail($response, rtrim($content_type, 's'), $content_id),
			$response
		);
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
				'instance' => ['type' => 'string', 'description' => 'BookStack instance name'],
			],
			'required' => ['content_type', 'content_id', 'instance'],
		]
	)]
	public function bookstack_permissions_update(string $content_type, int $content_id, int $owner_id = 0, string $instance = ''): ToolResult
	{
		$client = $this->manager->getClient($instance);
		$data = [];
		if ($owner_id > 0) $data['owner_id'] = $owner_id;
		$response = $client->put("content-permissions/{$content_type}/{$content_id}", $data);
		return ToolResult::structured(
			"Permissions updated.\n\n" . ResponseFormatter::permissionsDetail($response, rtrim($content_type, 's'), $content_id),
			$response
		);
	}
}
