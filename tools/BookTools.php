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
use EnchiladaMCP\ToolResult;
use BookStack\InstanceManager;
use BookStack\ResponseFormatter;

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
		description: 'List all books visible to the authenticated user. Books are the top-level containers in BookStack. Returns a Markdown list with id, name, description, and a pagination hint when more results exist.',
		readOnlyHint: true,
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'count' => ['type' => 'integer', 'description' => 'Number of books to return (default 20, max 500)'],
				'offset' => ['type' => 'integer', 'description' => 'Number of books to skip for pagination'],
				'sort' => ['type' => 'string', 'description' => 'Sort field: name, created_at, updated_at'],
				'instance' => ['type' => 'string', 'description' => 'BookStack instance name'],
			],
		]
	)]
	public function bookstack_books_list(int $count = 20, int $offset = 0, string $sort = 'name', string $instance = ''): ToolResult
	{
		$client = $this->manager->getClient($instance);
		$response = $client->get('books', ['count' => min($count, 500), 'offset' => $offset, 'sort' => $sort]);
		return ToolResult::structured(ResponseFormatter::booksList($response, $offset, $sort), $response);
	}

	/**
	 * Get a specific book with its content hierarchy.
	 */
	#[McpTool(
		name: 'bookstack_books_read',
		description: 'Get details of a specific book including its complete content hierarchy (chapters and pages) as Markdown. Use this to explore what is inside a book.',
		readOnlyHint: true,
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'id' => ['type' => 'integer', 'description' => 'The unique ID of the book'],
				'instance' => ['type' => 'string', 'description' => 'BookStack instance name'],
			],
			'required' => ['id', 'instance'],
		]
	)]
	public function bookstack_books_read(int $id, string $instance = ''): ToolResult
	{
		$client = $this->manager->getClient($instance);
		$response = $client->get("books/{$id}");
		return ToolResult::structured(ResponseFormatter::bookDetail($response), $response);
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
				'instance' => ['type' => 'string', 'description' => 'BookStack instance name'],
			],
			'required' => ['name', 'instance'],
		]
	)]
	public function bookstack_books_create(string $name, string $description = '', string $instance = ''): ToolResult
	{
		$client = $this->manager->getClient($instance);
		$data = ['name' => $name];
		if (!empty($description)) {
			$data['description'] = $description;
		}
		$response = $client->post('books', $data);
		return ToolResult::structured(
			ResponseFormatter::mutationSummary('Book created.', 'book', $response),
			$response
		);
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
				'instance' => ['type' => 'string', 'description' => 'BookStack instance name'],
			],
			'required' => ['id', 'instance'],
		]
	)]
	public function bookstack_books_update(int $id, string $name = '', string $description = '', string $instance = ''): ToolResult
	{
		$client = $this->manager->getClient($instance);
		$data = [];
		if (!empty($name)) $data['name'] = $name;
		if (!empty($description)) $data['description'] = $description;
		$response = $client->put("books/{$id}", $data);
		return ToolResult::structured(
			ResponseFormatter::mutationSummary('Book updated.', 'book', $response),
			$response
		);
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
				'instance' => ['type' => 'string', 'description' => 'BookStack instance name'],
			],
			'required' => ['id', 'instance'],
		]
	)]
	public function bookstack_books_delete(int $id, string $instance = ''): ToolResult
	{
		$client = $this->manager->getClient($instance);
		$client->delete("books/{$id}");
		return ToolResult::structured(
			ResponseFormatter::deleted('book', $id),
			['id' => $id, 'deleted' => true]
		);
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
				'instance' => ['type' => 'string', 'description' => 'BookStack instance name'],
			],
			'required' => ['id', 'format', 'instance'],
		]
	)]
	public function bookstack_books_export(int $id, string $format = 'markdown', string $instance = ''): ToolResult
	{
		$client = $this->manager->getClient($instance);
		$response = $client->get("books/{$id}/export/{$format}");
		$text = is_string($response) ? $response : json_encode($response);
		return ToolResult::structured($text, ['id' => $id, 'format' => $format, 'content' => $text]);
	}
}
