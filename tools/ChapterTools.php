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
		description: 'List chapters with id, name, parent book.',
		readOnlyHint: true,
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'count' => ['type' => 'integer', 'description' => 'default 20, max 500'],
				'offset' => ['type' => 'integer'],
				'sort' => ['type' => 'string', 'description' => 'name (default), created_at, updated_at, priority'],
				'instance' => ['type' => 'string', 'description' => 'Required BookStack instance name (no default; see bookstack_list_instances)'],
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
		description: 'Get a chapter and the list of its pages.',
		readOnlyHint: true,
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'id' => ['type' => 'integer'],
				'instance' => ['type' => 'string', 'description' => 'Required BookStack instance name (no default; see bookstack_list_instances)'],
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
		description: 'Create a chapter in a book.',
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'book_id' => ['type' => 'integer'],
				'name' => ['type' => 'string'],
				'description' => ['type' => 'string'],
				'instance' => ['type' => 'string', 'description' => 'Required BookStack instance name (no default; see bookstack_list_instances)'],
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
		description: 'Update a chapter\'s name or description, or move it to another book.',
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'id' => ['type' => 'integer'],
				'name' => ['type' => 'string'],
				'description' => ['type' => 'string'],
				'book_id' => ['type' => 'integer', 'description' => 'Target book to move the chapter to'],
				'instance' => ['type' => 'string', 'description' => 'Required BookStack instance name (no default; see bookstack_list_instances)'],
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
		description: 'Move a chapter and all its pages to the recycle bin.',
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'id' => ['type' => 'integer'],
				'instance' => ['type' => 'string', 'description' => 'Required BookStack instance name (no default; see bookstack_list_instances)'],
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
		description: 'Export a whole chapter.',
		readOnlyHint: true,
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'id' => ['type' => 'integer'],
				'format' => ['type' => 'string', 'description' => 'markdown or plaintext (best for LLMs), html, pdf'],
				'instance' => ['type' => 'string', 'description' => 'Required BookStack instance name (no default; see bookstack_list_instances)'],
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
