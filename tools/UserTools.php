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
use BookStack\InstanceManager;

class UserTools
{
	private InstanceManager $manager;

	public function __construct(InstanceManager $manager)
	{
		$this->manager = $manager;
	}

	#[McpTool(
		name: 'bookstack_users_list',
		description: 'List all users.',
		readOnlyHint: true,
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'count' => ['type' => 'integer', 'description' => 'Number of users to return (default 20, max 500)'],
				'offset' => ['type' => 'integer', 'description' => 'Pagination offset'],
				'sort' => ['type' => 'string', 'description' => 'Sort field: name, email, created_at, updated_at'],
				'instance' => ['type' => 'string', 'description' => 'BookStack instance name'],
			],
		]
	)]
	public function bookstack_users_list(int $count = 20, int $offset = 0, string $sort = 'name', string $instance = ''): array
	{
		$client = $this->manager->getClient($instance);
		return $client->get('users', ['count' => min($count, 500), 'offset' => $offset, 'sort' => $sort]);
	}

	#[McpTool(
		name: 'bookstack_users_read',
		description: 'Get details of a specific user, including their assigned roles.',
		readOnlyHint: true,
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'id' => ['type' => 'integer', 'description' => 'ID of the user'],
				'instance' => ['type' => 'string', 'description' => 'BookStack instance name'],
			],
			'required' => ['id', 'instance'],
		]
	)]
	public function bookstack_users_read(int $id, string $instance = ''): array
	{
		$client = $this->manager->getClient($instance);
		return $client->get("users/{$id}");
	}

	#[McpTool(
		name: 'bookstack_users_create',
		description: 'Create a new user account.',
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'name' => ['type' => 'string', 'description' => 'Display name'],
				'email' => ['type' => 'string', 'description' => 'Email address (must be unique)'],
				'password' => ['type' => 'string', 'description' => 'Initial password (min 8 chars)'],
				'roles' => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'List of role IDs to assign'],
				'send_invite' => ['type' => 'boolean', 'description' => 'Send email invitation (default false)'],
				'instance' => ['type' => 'string', 'description' => 'BookStack instance name'],
			],
			'required' => ['name', 'email', 'instance'],
		]
	)]
	public function bookstack_users_create(string $name, string $email, string $password = '', array $roles = [], bool $send_invite = false, string $instance = ''): array
	{
		$client = $this->manager->getClient($instance);
		$data = ['name' => $name, 'email' => $email];
		if (!empty($password)) $data['password'] = $password;
		if (!empty($roles)) $data['roles'] = $roles;
		if ($send_invite) $data['send_invite'] = true;
		return $client->post('users', $data);
	}

	#[McpTool(
		name: 'bookstack_users_update',
		description: 'Update a user\'s profile or roles.',
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'id' => ['type' => 'integer', 'description' => 'ID of the user to update'],
				'name' => ['type' => 'string', 'description' => 'New display name'],
				'email' => ['type' => 'string', 'description' => 'New email'],
				'roles' => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'New role IDs (replaces existing)'],
				'instance' => ['type' => 'string', 'description' => 'BookStack instance name'],
			],
			'required' => ['id', 'instance'],
		]
	)]
	public function bookstack_users_update(int $id, string $name = '', string $email = '', array $roles = [], string $instance = ''): array
	{
		$client = $this->manager->getClient($instance);
		$data = [];
		if (!empty($name)) $data['name'] = $name;
		if (!empty($email)) $data['email'] = $email;
		if (!empty($roles)) $data['roles'] = $roles;
		return $client->put("users/{$id}", $data);
	}

	#[McpTool(
		name: 'bookstack_users_delete',
		description: 'Delete a user account. Optionally transfer their content to another user.',
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'id' => ['type' => 'integer', 'description' => 'ID of the user to delete'],
				'migrate_ownership_id' => ['type' => 'integer', 'description' => 'ID of user to inherit content'],
				'instance' => ['type' => 'string', 'description' => 'BookStack instance name'],
			],
			'required' => ['id', 'instance'],
		]
	)]
	public function bookstack_users_delete(int $id, int $migrate_ownership_id = 0, string $instance = ''): array
	{
		$client = $this->manager->getClient($instance);
		$path = "users/{$id}";
		if ($migrate_ownership_id > 0) {
			$path .= "?migrate_ownership_id={$migrate_ownership_id}";
		}
		return $client->delete($path);
	}
}
