<?php
/**
 * BookStack MCP Server — Role Tools
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

class RoleTools
{
	private InstanceManager $manager;

	public function __construct(InstanceManager $manager)
	{
		$this->manager = $manager;
	}

	#[McpTool(
		name: 'bookstack_roles_list',
		description: 'List user roles.',
		readOnlyHint: true,
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'count' => ['type' => 'integer', 'description' => 'default 20, max 500'],
				'offset' => ['type' => 'integer'],
				'instance' => ['type' => 'string', 'description' => 'Required'],
			],
		]
	)]
	public function bookstack_roles_list(int $count = 20, int $offset = 0, string $instance = ''): ToolResult
	{
		$client = $this->manager->getClient($instance);
		$response = $client->get('roles', ['count' => min($count, 500), 'offset' => $offset]);
		return ToolResult::structured(ResponseFormatter::rolesList($response, $offset), $response);
	}

	#[McpTool(
		name: 'bookstack_roles_read',
		description: 'Get a role and its permissions.',
		readOnlyHint: true,
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'id' => ['type' => 'integer'],
				'instance' => ['type' => 'string'],
			],
			'required' => ['id', 'instance'],
		]
	)]
	public function bookstack_roles_read(int $id, string $instance = ''): ToolResult
	{
		$client = $this->manager->getClient($instance);
		$response = $client->get("roles/{$id}");
		return ToolResult::structured(ResponseFormatter::roleDetail($response), $response);
	}

	#[McpTool(
		name: 'bookstack_roles_create',
		description: 'Create a user role.',
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'display_name' => ['type' => 'string'],
				'description' => ['type' => 'string'],
				'instance' => ['type' => 'string'],
			],
			'required' => ['display_name', 'instance'],
		]
	)]
	public function bookstack_roles_create(string $display_name, string $description = '', string $instance = ''): ToolResult
	{
		$client = $this->manager->getClient($instance);
		$data = ['display_name' => $display_name];
		if (!empty($description)) $data['description'] = $description;
		$response = $client->post('roles', $data);
		return ToolResult::structured(
			ResponseFormatter::mutationSummary('Role created.', 'role', $response),
			$response
		);
	}

	#[McpTool(
		name: 'bookstack_roles_update',
		description: 'Update a role\'s display name or description.',
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'id' => ['type' => 'integer'],
				'display_name' => ['type' => 'string'],
				'description' => ['type' => 'string'],
				'instance' => ['type' => 'string'],
			],
			'required' => ['id', 'instance'],
		]
	)]
	public function bookstack_roles_update(int $id, string $display_name = '', string $description = '', string $instance = ''): ToolResult
	{
		$client = $this->manager->getClient($instance);
		$data = [];
		if (!empty($display_name)) $data['display_name'] = $display_name;
		if (!empty($description)) $data['description'] = $description;
		$response = $client->put("roles/{$id}", $data);
		return ToolResult::structured(
			ResponseFormatter::mutationSummary('Role updated.', 'role', $response),
			$response
		);
	}

	#[McpTool(
		name: 'bookstack_roles_delete',
		description: 'Delete a role.',
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'id' => ['type' => 'integer'],
				'migrate_ownership_id' => ['type' => 'integer', 'description' => 'Role that receives this role\'s users'],
				'instance' => ['type' => 'string'],
			],
			'required' => ['id', 'instance'],
		]
	)]
	public function bookstack_roles_delete(int $id, int $migrate_ownership_id = 0, string $instance = ''): ToolResult
	{
		$client = $this->manager->getClient($instance);
		$path = "roles/{$id}";
		if ($migrate_ownership_id > 0) {
			$path .= "?migrate_ownership_id={$migrate_ownership_id}";
		}
		$client->delete($path);
		return ToolResult::structured(
			ResponseFormatter::deleted('role', $id, false),
			['id' => $id, 'deleted' => true]
		);
	}
}
