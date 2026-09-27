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
use EnchiladaMCP\ToolResult;
use BookStack\InstanceManager;
use BookStack\ResponseFormatter;

class ShelfTools
{
	private InstanceManager $manager;

	public function __construct(InstanceManager $manager)
	{
		$this->manager = $manager;
	}

	#[McpTool(
		name: 'bookstack_shelves_list',
		description: 'List shelves (collections of books).',
		readOnlyHint: true,
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'count' => ['type' => 'integer', 'description' => 'default 20, max 500'],
				'offset' => ['type' => 'integer'],
				'sort' => ['type' => 'string', 'description' => 'name, created_at, updated_at'],
				'instance' => ['type' => 'string', 'description' => 'Required'],
			],
		]
	)]
	public function bookstack_shelves_list(int $count = 20, int $offset = 0, string $sort = 'name', string $instance = ''): ToolResult
	{
		$client = $this->manager->getClient($instance);
		$response = $client->get('shelves', ['count' => min($count, 500), 'offset' => $offset, 'sort' => $sort]);
		return ToolResult::structured(ResponseFormatter::shelvesList($response, $offset, $sort), $response);
	}

	#[McpTool(
		name: 'bookstack_shelves_read',
		description: 'Get a shelf and its books.',
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
	public function bookstack_shelves_read(int $id, string $instance = ''): ToolResult
	{
		$client = $this->manager->getClient($instance);
		$response = $client->get("shelves/{$id}");
		return ToolResult::structured(ResponseFormatter::shelfDetail($response), $response);
	}

	#[McpTool(
		name: 'bookstack_shelves_create',
		description: 'Create a shelf.',
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'name' => ['type' => 'string'],
				'description' => ['type' => 'string'],
				'books' => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'Book IDs'],
				'instance' => ['type' => 'string'],
			],
			'required' => ['name', 'instance'],
		]
	)]
	public function bookstack_shelves_create(string $name, string $description = '', array $books = [], string $instance = ''): ToolResult
	{
		$client = $this->manager->getClient($instance);
		$data = ['name' => $name];
		if (!empty($description)) $data['description'] = $description;
		if (!empty($books)) $data['books'] = $books;
		$response = $client->post('shelves', $data);
		return ToolResult::structured(
			ResponseFormatter::mutationSummary('Shelf created.', 'bookshelf', $response),
			$response
		);
	}

	#[McpTool(
		name: 'bookstack_shelves_update',
		description: 'Update a shelf\'s name, description or books.',
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'id' => ['type' => 'integer'],
				'name' => ['type' => 'string'],
				'description' => ['type' => 'string'],
				'books' => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'Book IDs; replaces the whole list'],
				'instance' => ['type' => 'string'],
			],
			'required' => ['id', 'instance'],
		]
	)]
	public function bookstack_shelves_update(int $id, string $name = '', string $description = '', array $books = [], string $instance = ''): ToolResult
	{
		$client = $this->manager->getClient($instance);
		$data = [];
		if (!empty($name)) $data['name'] = $name;
		if (!empty($description)) $data['description'] = $description;
		if (!empty($books)) $data['books'] = $books;
		$response = $client->put("shelves/{$id}", $data);
		return ToolResult::structured(
			ResponseFormatter::mutationSummary('Shelf updated.', 'bookshelf', $response),
			$response
		);
	}

	#[McpTool(
		name: 'bookstack_shelves_delete',
		description: 'Delete a shelf; its books are kept.',
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'id' => ['type' => 'integer'],
				'instance' => ['type' => 'string'],
			],
			'required' => ['id', 'instance'],
		]
	)]
	public function bookstack_shelves_delete(int $id, string $instance = ''): ToolResult
	{
		$client = $this->manager->getClient($instance);
		$client->delete("shelves/{$id}");
		return ToolResult::structured(
			ResponseFormatter::deleted('bookshelf', $id),
			['id' => $id, 'deleted' => true]
		);
	}
}
