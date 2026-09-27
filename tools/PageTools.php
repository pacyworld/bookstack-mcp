<?php
/**
 * BookStack MCP Server — Page Tools
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

class PageTools
{
	private InstanceManager $manager;

	public function __construct(InstanceManager $manager)
	{
		$this->manager = $manager;
	}

	/**
	 * List all pages.
	 */
	#[McpTool(
		name: 'bookstack_pages_list',
		description: 'List pages with id, name, parent book/chapter.',
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
	public function bookstack_pages_list(int $count = 20, int $offset = 0, string $sort = 'name', string $instance = ''): ToolResult
	{
		$client = $this->manager->getClient($instance);
		$response = $client->get('pages', ['count' => min($count, 500), 'offset' => $offset, 'sort' => $sort]);
		return ToolResult::structured(ResponseFormatter::pagesList($response, $offset, $sort), $response);
	}

	/**
	 * Get full page details and content.
	 */
	#[McpTool(
		name: 'bookstack_pages_read',
		description: 'Get a page\'s full content as Markdown (HTML body for HTML-authored pages) with breadcrumb, tags and dates.',
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
	public function bookstack_pages_read(int $id, string $instance = ''): ToolResult
	{
		$client = $this->manager->getClient($instance);
		$response = $client->get("pages/{$id}");
		return ToolResult::structured(ResponseFormatter::pageDetail($response), $response);
	}

	/**
	 * Create a new page.
	 */
	#[McpTool(
		name: 'bookstack_pages_create',
		description: 'Create a page. Give book_id or chapter_id, and markdown (preferred) or html, not both.',
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'name' => ['type' => 'string'],
				'book_id' => ['type' => 'integer'],
				'chapter_id' => ['type' => 'integer'],
				'markdown' => ['type' => 'string'],
				'html' => ['type' => 'string'],
				'instance' => ['type' => 'string'],
			],
			'required' => ['name', 'instance'],
		]
	)]
	public function bookstack_pages_create(string $name, int $book_id = 0, int $chapter_id = 0, string $markdown = '', string $html = '', string $instance = ''): ToolResult
	{
		$client = $this->manager->getClient($instance);
		$data = ['name' => $name];
		if ($book_id > 0) $data['book_id'] = $book_id;
		if ($chapter_id > 0) $data['chapter_id'] = $chapter_id;
		if (!empty($markdown)) $data['markdown'] = $markdown;
		elseif (!empty($html)) $data['html'] = $html;
		$response = $client->post('pages', $data);
		return ToolResult::structured(
			ResponseFormatter::mutationSummary('Page created.', 'page', $response),
			$response
		);
	}

	/**
	 * Update a page.
	 */
	#[McpTool(
		name: 'bookstack_pages_update',
		description: 'Update a page\'s name and/or content. New content REPLACES the whole page, so read it first for partial edits.',
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'id' => ['type' => 'integer'],
				'name' => ['type' => 'string'],
				'markdown' => ['type' => 'string'],
				'html' => ['type' => 'string'],
				'instance' => ['type' => 'string'],
			],
			'required' => ['id', 'instance'],
		]
	)]
	public function bookstack_pages_update(int $id, string $name = '', string $markdown = '', string $html = '', string $instance = ''): ToolResult
	{
		$client = $this->manager->getClient($instance);
		$data = [];
		if (!empty($name)) $data['name'] = $name;
		if (!empty($markdown)) $data['markdown'] = $markdown;
		elseif (!empty($html)) $data['html'] = $html;
		$response = $client->put("pages/{$id}", $data);
		return ToolResult::structured(
			ResponseFormatter::mutationSummary('Page updated.', 'page', $response),
			$response
		);
	}

	/**
	 * Delete a page.
	 */
	#[McpTool(
		name: 'bookstack_pages_delete',
		description: 'Move a page to the recycle bin.',
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'id' => ['type' => 'integer'],
				'instance' => ['type' => 'string'],
			],
			'required' => ['id', 'instance'],
		]
	)]
	public function bookstack_pages_delete(int $id, string $instance = ''): ToolResult
	{
		$client = $this->manager->getClient($instance);
		$client->delete("pages/{$id}");
		return ToolResult::structured(
			ResponseFormatter::deleted('page', $id),
			['id' => $id, 'deleted' => true]
		);
	}

	/**
	 * Export a page.
	 */
	#[McpTool(
		name: 'bookstack_pages_export',
		description: 'Export a page (e.g. raw HTML with format=html).',
		readOnlyHint: true,
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'id' => ['type' => 'integer'],
				'format' => ['type' => 'string', 'description' => 'markdown or plaintext (best for LLMs), html, pdf'],
				'instance' => ['type' => 'string'],
			],
			'required' => ['id', 'format', 'instance'],
		]
	)]
	public function bookstack_pages_export(int $id, string $format = 'markdown', string $instance = ''): ToolResult
	{
		$client = $this->manager->getClient($instance);
		$response = $client->get("pages/{$id}/export/{$format}");
		$text = is_string($response) ? $response : json_encode($response);
		return ToolResult::structured($text, ['id' => $id, 'format' => $format, 'content' => $text]);
	}
}
