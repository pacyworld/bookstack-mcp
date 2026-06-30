<?php
/**
 * BookStack MCP Server — Book Tools
 *
 * @package    BookstackMCP\Tools
 * @author     Daniel Morante
 * @copyright  2026 The Daniel Morante Company, Inc.
 * @license    BSD-2-Clause
 */

use EnchiladaMCP\McpTool;
use BookStack\InstanceManager;

class BookTools
{
	private InstanceManager $manager;

	public function __construct(InstanceManager $manager)
	{
		$this->manager = $manager;
	}

	/**
	 * List all books.
	 */
	#[McpTool(
		name: 'bookstack_books_list',
		description: 'List all books visible to the authenticated user. Books are the top-level containers in BookStack.',
		readOnlyHint: true,
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'count' => ['type' => 'integer', 'description' => 'Number of books to return (default 20, max 500)'],
				'offset' => ['type' => 'integer', 'description' => 'Number of books to skip for pagination'],
				'sort' => ['type' => 'string', 'description' => 'Sort field: name, created_at, updated_at'],
				'instance' => ['type' => 'string', 'description' => 'BookStack instance name (optional)'],
			],
		]
	)]
	public function bookstack_books_list(int $count = 20, int $offset = 0, string $sort = 'name', string $instance = ''): array
	{
		$client = $this->manager->getClient($instance ?: null);
		return $client->get('books', ['count' => min($count, 500), 'offset' => $offset, 'sort' => $sort]);
	}

	/**
	 * Get a specific book with its content hierarchy.
	 */
	#[McpTool(
		name: 'bookstack_books_read',
		description: 'Get details of a specific book including its complete content hierarchy (chapters and pages). Use this to explore what is inside a book.',
		readOnlyHint: true,
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'id' => ['type' => 'integer', 'description' => 'The unique ID of the book'],
				'instance' => ['type' => 'string', 'description' => 'BookStack instance name (optional)'],
			],
			'required' => ['id'],
		]
	)]
	public function bookstack_books_read(int $id, string $instance = ''): array
	{
		$client = $this->manager->getClient($instance ?: null);
		return $client->get("books/{$id}");
	}

	/**
	 * Create a new book.
	 */
	#[McpTool(
		name: 'bookstack_books_create',
		description: 'Create a new book. Books are the top-level containers for chapters and pages.',
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'name' => ['type' => 'string', 'description' => 'The name of the book'],
				'description' => ['type' => 'string', 'description' => 'Plain text description'],
				'instance' => ['type' => 'string', 'description' => 'BookStack instance name (optional)'],
			],
			'required' => ['name'],
		]
	)]
	public function bookstack_books_create(string $name, string $description = '', string $instance = ''): array
	{
		$client = $this->manager->getClient($instance ?: null);
		$data = ['name' => $name];
		if (!empty($description)) {
			$data['description'] = $description;
		}
		return $client->post('books', $data);
	}

	/**
	 * Update an existing book.
	 */
	#[McpTool(
		name: 'bookstack_books_update',
		description: 'Update a book\'s name, description, or tags.',
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'id' => ['type' => 'integer', 'description' => 'ID of the book to update'],
				'name' => ['type' => 'string', 'description' => 'New book name'],
				'description' => ['type' => 'string', 'description' => 'New description'],
				'instance' => ['type' => 'string', 'description' => 'BookStack instance name (optional)'],
			],
			'required' => ['id'],
		]
	)]
	public function bookstack_books_update(int $id, string $name = '', string $description = '', string $instance = ''): array
	{
		$client = $this->manager->getClient($instance ?: null);
		$data = [];
		if (!empty($name)) $data['name'] = $name;
		if (!empty($description)) $data['description'] = $description;
		return $client->put("books/{$id}", $data);
	}

	/**
	 * Delete a book.
	 */
	#[McpTool(
		name: 'bookstack_books_delete',
		description: 'Delete a book. Moves the book and all its contents to the recycle bin.',
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'id' => ['type' => 'integer', 'description' => 'ID of the book to delete'],
				'instance' => ['type' => 'string', 'description' => 'BookStack instance name (optional)'],
			],
			'required' => ['id'],
		]
	)]
	public function bookstack_books_delete(int $id, string $instance = ''): array
	{
		$client = $this->manager->getClient($instance ?: null);
		return $client->delete("books/{$id}");
	}

	/**
	 * Export a book.
	 */
	#[McpTool(
		name: 'bookstack_books_export',
		description: 'Export a book to a specific format. Use "markdown" or "plaintext" for LLM-friendly output.',
		readOnlyHint: true,
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'id' => ['type' => 'integer', 'description' => 'ID of the book to export'],
				'format' => ['type' => 'string', 'description' => 'Export format: html, pdf, plaintext, markdown'],
				'instance' => ['type' => 'string', 'description' => 'BookStack instance name (optional)'],
			],
			'required' => ['id', 'format'],
		]
	)]
	public function bookstack_books_export(int $id, string $format = 'markdown', string $instance = ''): string
	{
		$client = $this->manager->getClient($instance ?: null);
		$response = $client->get("books/{$id}/export/{$format}");
		return is_string($response) ? $response : json_encode($response);
	}
}
