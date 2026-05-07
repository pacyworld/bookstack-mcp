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
use BookStack\InstanceManager;

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
		description: 'List all pages visible to the authenticated user with pagination and filtering.',
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'count' => ['type' => 'integer', 'description' => 'Number of pages to return (default 20, max 500)'],
				'offset' => ['type' => 'integer', 'description' => 'Number of pages to skip for pagination'],
				'sort' => ['type' => 'string', 'description' => 'Sort field: name, created_at, updated_at'],
				'instance' => ['type' => 'string', 'description' => 'BookStack instance name (optional)'],
			],
		]
	)]
	public function bookstack_pages_list(int $count = 20, int $offset = 0, string $sort = 'name', string $instance = ''): array
	{
		$client = $this->manager->getClient($instance ?: null);
		return $client->get('pages', ['count' => min($count, 500), 'offset' => $offset, 'sort' => $sort]);
	}

	/**
	 * Get full page details and content.
	 */
	#[McpTool(
		name: 'bookstack_pages_read',
		description: 'Get the full details and content of a page. Includes raw HTML and Markdown content.',
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'id' => ['type' => 'integer', 'description' => 'The unique ID of the page'],
				'instance' => ['type' => 'string', 'description' => 'BookStack instance name (optional)'],
			],
			'required' => ['id'],
		]
	)]
	public function bookstack_pages_read(int $id, string $instance = ''): array
	{
		$client = $this->manager->getClient($instance ?: null);
		return $client->get("pages/{$id}");
	}

	/**
	 * Create a new page.
	 */
	#[McpTool(
		name: 'bookstack_pages_create',
		description: 'Create a new page. Provide content in Markdown (preferred) or HTML. Must specify a parent book_id or chapter_id.',
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'name' => ['type' => 'string', 'description' => 'Title of the page'],
				'book_id' => ['type' => 'integer', 'description' => 'Parent book ID (required if chapter_id not provided)'],
				'chapter_id' => ['type' => 'integer', 'description' => 'Parent chapter ID (required if book_id not provided)'],
				'markdown' => ['type' => 'string', 'description' => 'Page content in Markdown (preferred for LLM generation)'],
				'html' => ['type' => 'string', 'description' => 'Page content in HTML (use this OR markdown, not both)'],
				'instance' => ['type' => 'string', 'description' => 'BookStack instance name (optional)'],
			],
			'required' => ['name'],
		]
	)]
	public function bookstack_pages_create(string $name, int $book_id = 0, int $chapter_id = 0, string $markdown = '', string $html = '', string $instance = ''): array
	{
		$client = $this->manager->getClient($instance ?: null);
		$data = ['name' => $name];
		if ($book_id > 0) $data['book_id'] = $book_id;
		if ($chapter_id > 0) $data['chapter_id'] = $chapter_id;
		if (!empty($markdown)) $data['markdown'] = $markdown;
		elseif (!empty($html)) $data['html'] = $html;
		return $client->post('pages', $data);
	}

	/**
	 * Update a page.
	 */
	#[McpTool(
		name: 'bookstack_pages_update',
		description: 'Update a page\'s content or properties. Always read the page first if you intend to modify partially, as this replaces the content field entirely.',
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'id' => ['type' => 'integer', 'description' => 'ID of the page to update'],
				'name' => ['type' => 'string', 'description' => 'New page name'],
				'markdown' => ['type' => 'string', 'description' => 'New Markdown content (replaces existing)'],
				'html' => ['type' => 'string', 'description' => 'New HTML content (replaces existing)'],
				'instance' => ['type' => 'string', 'description' => 'BookStack instance name (optional)'],
			],
			'required' => ['id'],
		]
	)]
	public function bookstack_pages_update(int $id, string $name = '', string $markdown = '', string $html = '', string $instance = ''): array
	{
		$client = $this->manager->getClient($instance ?: null);
		$data = [];
		if (!empty($name)) $data['name'] = $name;
		if (!empty($markdown)) $data['markdown'] = $markdown;
		elseif (!empty($html)) $data['html'] = $html;
		return $client->put("pages/{$id}", $data);
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
				'id' => ['type' => 'integer', 'description' => 'ID of the page to delete'],
				'instance' => ['type' => 'string', 'description' => 'BookStack instance name (optional)'],
			],
			'required' => ['id'],
		]
	)]
	public function bookstack_pages_delete(int $id, string $instance = ''): array
	{
		$client = $this->manager->getClient($instance ?: null);
		return $client->delete("pages/{$id}");
	}

	/**
	 * Export a page.
	 */
	#[McpTool(
		name: 'bookstack_pages_export',
		description: 'Export a page to a specific format. Use "markdown" or "plaintext" for LLM-friendly output.',
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'id' => ['type' => 'integer', 'description' => 'ID of the page to export'],
				'format' => ['type' => 'string', 'description' => 'Export format: html, pdf, plaintext, markdown'],
				'instance' => ['type' => 'string', 'description' => 'BookStack instance name (optional)'],
			],
			'required' => ['id', 'format'],
		]
	)]
	public function bookstack_pages_export(int $id, string $format = 'markdown', string $instance = ''): string
	{
		$client = $this->manager->getClient($instance ?: null);
		$response = $client->get("pages/{$id}/export/{$format}");
		return is_string($response) ? $response : json_encode($response);
	}
}
