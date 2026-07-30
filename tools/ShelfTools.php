<?php
/**
 * BookStack MCP Server — Shelf Tools
 *
 * @package    BookstackMCP\Tools
 * @author     Daniel Morante
 * @copyright  2026 The Daniel Morante Company, Inc.
 * @license    BSD-2-Clause
 */

use EnchiladaMCP\McpTool;
use BookStack\InstanceManager;

class ShelfTools
{
	private InstanceManager $manager;

	public function __construct(InstanceManager $manager)
	{
		$this->manager = $manager;
	}

	#[McpTool(
		name: 'bookstack_shelves_list',
		description: 'List all bookshelves. Shelves organize books into collections.',
		readOnlyHint: true,
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'count' => ['type' => 'integer', 'description' => 'Number of shelves to return (default 20, max 500)'],
				'offset' => ['type' => 'integer', 'description' => 'Pagination offset'],
				'sort' => ['type' => 'string', 'description' => 'Sort field: name, created_at, updated_at'],
				'instance' => ['type' => 'string', 'description' => 'BookStack instance name'],
			],
		]
	)]
	public function bookstack_shelves_list(int $count = 20, int $offset = 0, string $sort = 'name', string $instance = ''): array
	{
		$client = $this->manager->getClient($instance);
		return $client->get('shelves', ['count' => min($count, 500), 'offset' => $offset, 'sort' => $sort]);
	}

	#[McpTool(
		name: 'bookstack_shelves_read',
		description: 'Get details of a specific bookshelf, including the list of books assigned to it.',
		readOnlyHint: true,
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'id' => ['type' => 'integer', 'description' => 'The unique ID of the shelf'],
				'instance' => ['type' => 'string', 'description' => 'BookStack instance name'],
			],
			'required' => ['id', 'instance'],
		]
	)]
	public function bookstack_shelves_read(int $id, string $instance = ''): array
	{
		$client = $this->manager->getClient($instance);
		return $client->get("shelves/{$id}");
	}

	#[McpTool(
		name: 'bookstack_shelves_create',
		description: 'Create a new bookshelf to group related books.',
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'name' => ['type' => 'string', 'description' => 'Name of the shelf'],
				'description' => ['type' => 'string', 'description' => 'Short description'],
				'books' => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'List of book IDs to include'],
				'instance' => ['type' => 'string', 'description' => 'BookStack instance name'],
			],
			'required' => ['name', 'instance'],
		]
	)]
	public function bookstack_shelves_create(string $name, string $description = '', array $books = [], string $instance = ''): array
	{
		$client = $this->manager->getClient($instance);
		$data = ['name' => $name];
		if (!empty($description)) $data['description'] = $description;
		if (!empty($books)) $data['books'] = $books;
		return $client->post('shelves', $data);
	}

	#[McpTool(
		name: 'bookstack_shelves_update',
		description: 'Update a bookshelf\'s name, description, or book list.',
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'id' => ['type' => 'integer', 'description' => 'ID of the shelf to update'],
				'name' => ['type' => 'string', 'description' => 'New shelf name'],
				'description' => ['type' => 'string', 'description' => 'New description'],
				'books' => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'New list of book IDs (replaces all)'],
				'instance' => ['type' => 'string', 'description' => 'BookStack instance name'],
			],
			'required' => ['id', 'instance'],
		]
	)]
	public function bookstack_shelves_update(int $id, string $name = '', string $description = '', array $books = [], string $instance = ''): array
	{
		$client = $this->manager->getClient($instance);
		$data = [];
		if (!empty($name)) $data['name'] = $name;
		if (!empty($description)) $data['description'] = $description;
		if (!empty($books)) $data['books'] = $books;
		return $client->put("shelves/{$id}", $data);
	}

	#[McpTool(
		name: 'bookstack_shelves_delete',
		description: 'Delete a bookshelf. This only removes the shelf container; books remain safe.',
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'id' => ['type' => 'integer', 'description' => 'ID of the shelf to delete'],
				'instance' => ['type' => 'string', 'description' => 'BookStack instance name'],
			],
			'required' => ['id', 'instance'],
		]
	)]
	public function bookstack_shelves_delete(int $id, string $instance = ''): array
	{
		$client = $this->manager->getClient($instance);
		return $client->delete("shelves/{$id}");
	}
}
