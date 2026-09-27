<?php
/**
 * BookStack MCP Server — User Tools
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

class UserTools
{
	private InstanceManager $manager;

	public function __construct(InstanceManager $manager)
	{
		$this->manager = $manager;
	}

	#[McpTool(
		name: 'bookstack_users_list',
		description: 'List users.',
		readOnlyHint: true,
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'count' => ['type' => 'integer', 'description' => 'default 20, max 500'],
				'offset' => ['type' => 'integer'],
				'sort' => ['type' => 'string', 'description' => 'name, email, created_at, updated_at'],
				'instance' => ['type' => 'string', 'description' => 'Required'],
			],
		]
	)]
	public function bookstack_users_list(int $count = 20, int $offset = 0, string $sort = 'name', string $instance = ''): ToolResult
	{
		$client = $this->manager->getClient($instance);
		$response = $client->get('users', ['count' => min($count, 500), 'offset' => $offset, 'sort' => $sort]);
		return ToolResult::structured(ResponseFormatter::usersList($response, $offset, $sort), $response);
	}

	#[McpTool(
		name: 'bookstack_users_read',
		description: 'Get a user and their roles.',
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
	public function bookstack_users_read(int $id, string $instance = ''): ToolResult
	{
		$client = $this->manager->getClient($instance);
		$response = $client->get("users/{$id}");
		return ToolResult::structured(ResponseFormatter::userDetail($response), $response);
	}

	#[McpTool(
		name: 'bookstack_users_create',
		description: 'Create a user account.',
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'name' => ['type' => 'string', 'description' => 'Display name'],
				'email' => ['type' => 'string', 'description' => 'Must be unique'],
				'password' => ['type' => 'string', 'description' => 'Min 8 chars'],
				'roles' => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'Role IDs'],
				'send_invite' => ['type' => 'boolean', 'description' => 'Email an invitation (default false)'],
				'instance' => ['type' => 'string'],
			],
			'required' => ['name', 'email', 'instance'],
		]
	)]
	public function bookstack_users_create(string $name, string $email, string $password = '', array $roles = [], bool $send_invite = false, string $instance = ''): ToolResult
	{
		$client = $this->manager->getClient($instance);
		$data = ['name' => $name, 'email' => $email];
		if (!empty($password)) $data['password'] = $password;
		if (!empty($roles)) $data['roles'] = $roles;
		if ($send_invite) $data['send_invite'] = true;
		$response = $client->post('users', $data);
		return ToolResult::structured(
			ResponseFormatter::mutationSummary('User created.', 'user', $response),
			$response
		);
	}

	#[McpTool(
		name: 'bookstack_users_update',
		description: 'Update a user\'s name, email or roles.',
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'id' => ['type' => 'integer'],
				'name' => ['type' => 'string'],
				'email' => ['type' => 'string'],
				'roles' => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'Role IDs; replaces existing roles'],
				'instance' => ['type' => 'string'],
			],
			'required' => ['id', 'instance'],
		]
	)]
	public function bookstack_users_update(int $id, string $name = '', string $email = '', array $roles = [], string $instance = ''): ToolResult
	{
		$client = $this->manager->getClient($instance);
		$data = [];
		if (!empty($name)) $data['name'] = $name;
		if (!empty($email)) $data['email'] = $email;
		if (!empty($roles)) $data['roles'] = $roles;
		$response = $client->put("users/{$id}", $data);
		return ToolResult::structured(
			ResponseFormatter::mutationSummary('User updated.', 'user', $response),
			$response
		);
	}

	#[McpTool(
		name: 'bookstack_users_delete',
		description: 'Delete a user account.',
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'id' => ['type' => 'integer'],
				'migrate_ownership_id' => ['type' => 'integer', 'description' => 'User who inherits their content'],
				'instance' => ['type' => 'string'],
			],
			'required' => ['id', 'instance'],
		]
	)]
	public function bookstack_users_delete(int $id, int $migrate_ownership_id = 0, string $instance = ''): ToolResult
	{
		$client = $this->manager->getClient($instance);
		$path = "users/{$id}";
		if ($migrate_ownership_id > 0) {
			$path .= "?migrate_ownership_id={$migrate_ownership_id}";
		}
		$client->delete($path);
		return ToolResult::structured(
			ResponseFormatter::deleted('user', $id, false),
			['id' => $id, 'deleted' => true]
		);
	}
}
