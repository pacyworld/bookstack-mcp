<?php
/**
 * BookStack MCP Server — Chapter Tools
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

class ChapterTools
{
	private InstanceManager $manager;

	public function __construct(InstanceManager $manager)
	{
		$this->manager = $manager;
	}

	/**
	 * List all chapters.
	 */
	#[McpTool(
		name: 'bookstack_chapters_list',
		description: 'List all chapters visible to the authenticated user. Returns a Markdown list with id, name, parent book, and a pagination hint when more results exist.',
		readOnlyHint: true,
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'count' => ['type' => 'integer', 'description' => 'Number of chapters to return (default 20, max 500)'],
				'offset' => ['type' => 'integer', 'description' => 'Number of chapters to skip for pagination'],
				'sort' => ['type' => 'string', 'description' => 'Sort field: name, created_at, updated_at, priority'],
				'instance' => ['type' => 'string', 'description' => 'BookStack instance name'],
			],
		]
	)]
	public function bookstack_chapters_list(int $count = 20, int $offset = 0, string $sort = 'name', string $instance = ''): ToolResult
	{
		$client = $this->manager->getClient($instance);
		$response = $client->get('chapters', ['count' => min($count, 500), 'offset' => $offset, 'sort' => $sort]);
		return ToolResult::structured(ResponseFormatter::chaptersList($response, $offset, $sort), $response);
	}

	/**
	 * Get a specific chapter with its pages.
	 */
	#[McpTool(
		name: 'bookstack_chapters_read',
		description: 'Get details of a specific chapter as Markdown, including a list of pages contained within it.',
		readOnlyHint: true,
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'id' => ['type' => 'integer', 'description' => 'The unique ID of the chapter'],
				'instance' => ['type' => 'string', 'description' => 'BookStack instance name'],
			],
			'required' => ['id', 'instance'],
		]
	)]
	public function bookstack_chapters_read(int $id, string $instance = ''): ToolResult
	{
		$client = $this->manager->getClient($instance);
		$response = $client->get("chapters/{$id}");
		return ToolResult::structured(ResponseFormatter::chapterDetail($response), $response);
	}

	/**
	 * Create a new chapter.
	 */
	#[McpTool(
		name: 'bookstack_chapters_create',
		description: 'Create a new chapter within a book.',
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'book_id' => ['type' => 'integer', 'description' => 'ID of the book to contain this chapter'],
				'name' => ['type' => 'string', 'description' => 'Name of the chapter'],
				'description' => ['type' => 'string', 'description' => 'Short description'],
				'instance' => ['type' => 'string', 'description' => 'BookStack instance name'],
			],
			'required' => ['book_id', 'name', 'instance'],
		]
	)]
	public function bookstack_chapters_create(int $book_id, string $name, string $description = '', string $instance = ''): ToolResult
	{
		$client = $this->manager->getClient($instance);
		$data = ['book_id' => $book_id, 'name' => $name];
		if (!empty($description)) {
			$data['description'] = $description;
		}
		$response = $client->post('chapters', $data);
		return ToolResult::structured(
			ResponseFormatter::mutationSummary('Chapter created.', 'chapter', $response),
			$response
		);
	}

	/**
	 * Update an existing chapter.
	 */
	#[McpTool(
		name: 'bookstack_chapters_update',
		description: 'Update a chapter\'s name, description, or move it to a different book.',
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'id' => ['type' => 'integer', 'description' => 'ID of the chapter to update'],
				'name' => ['type' => 'string', 'description' => 'New chapter name'],
				'description' => ['type' => 'string', 'description' => 'New description'],
				'book_id' => ['type' => 'integer', 'description' => 'New parent book ID (to move the chapter)'],
				'instance' => ['type' => 'string', 'description' => 'BookStack instance name'],
			],
			'required' => ['id', 'instance'],
		]
	)]
	public function bookstack_chapters_update(int $id, string $name = '', string $description = '', int $book_id = 0, string $instance = ''): ToolResult
	{
		$client = $this->manager->getClient($instance);
		$data = [];
		if (!empty($name)) $data['name'] = $name;
		if (!empty($description)) $data['description'] = $description;
		if ($book_id > 0) $data['book_id'] = $book_id;
		$response = $client->put("chapters/{$id}", $data);
		return ToolResult::structured(
			ResponseFormatter::mutationSummary('Chapter updated.', 'chapter', $response),
			$response
		);
	}

	/**
	 * Delete a chapter.
	 */
	#[McpTool(
		name: 'bookstack_chapters_delete',
		description: 'Delete a chapter and all its pages (moved to recycle bin).',
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'id' => ['type' => 'integer', 'description' => 'ID of the chapter to delete'],
				'instance' => ['type' => 'string', 'description' => 'BookStack instance name'],
			],
			'required' => ['id', 'instance'],
		]
	)]
	public function bookstack_chapters_delete(int $id, string $instance = ''): ToolResult
	{
		$client = $this->manager->getClient($instance);
		$client->delete("chapters/{$id}");
		return ToolResult::structured(
			ResponseFormatter::deleted('chapter', $id),
			['id' => $id, 'deleted' => true]
		);
	}

	/**
	 * Export a chapter.
	 */
	#[McpTool(
		name: 'bookstack_chapters_export',
		description: 'Export a chapter to a specific format. Use "markdown" or "plaintext" for LLM-friendly output.',
		readOnlyHint: true,
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'id' => ['type' => 'integer', 'description' => 'ID of the chapter to export'],
				'format' => ['type' => 'string', 'description' => 'Export format: html, pdf, plaintext, markdown'],
				'instance' => ['type' => 'string', 'description' => 'BookStack instance name'],
			],
			'required' => ['id', 'format', 'instance'],
		]
	)]
	public function bookstack_chapters_export(int $id, string $format = 'markdown', string $instance = ''): ToolResult
	{
		$client = $this->manager->getClient($instance);
		$response = $client->get("chapters/{$id}/export/{$format}");
		$text = is_string($response) ? $response : json_encode($response);
		return ToolResult::structured($text, ['id' => $id, 'format' => $format, 'content' => $text]);
	}
}
