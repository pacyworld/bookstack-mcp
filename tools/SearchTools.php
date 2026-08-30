<?php
/**
 * BookStack MCP Server — Search Tools
 *
 * @package    BookstackMCP\Tools
 * @author     Daniel Morante
 * @copyright  2026 The Daniel Morante Company, Inc.
 * @license    BSD-2-Clause
 */

use EnchiladaMCP\McpTool;
use BookStack\InstanceManager;
use BookStack\ResponseFormatter;

class SearchTools
{
	private InstanceManager $manager;

	public function __construct(InstanceManager $manager)
	{
		$this->manager = $manager;
	}

	/**
	 * Search across all BookStack content.
	 */
	#[McpTool(
		name: 'bookstack_search',
		description: 'Search across all BookStack content. Supports advanced syntax: "exact phrase", {type:page|book|chapter|shelf}, {tag:name=value}, {created_by:me}. Returns Markdown-formatted results with breadcrumbs and highlighted snippets — snippets only, use bookstack_pages_read for full content. A footer indicates when more result pages exist.',
		readOnlyHint: true,
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'query' => ['type' => 'string', 'description' => 'Search query string with optional advanced syntax'],
				'count' => ['type' => 'integer', 'description' => 'Results per page (default 20, max 100)'],
				'page' => ['type' => 'integer', 'description' => 'Page number for pagination (default 1)'],
				'instance' => ['type' => 'string', 'description' => 'BookStack instance name'],
			],
			'required' => ['query', 'instance'],
		]
	)]
	public function bookstack_search(string $query, int $count = 20, int $page = 1, string $instance = ''): string
	{
		$client = $this->manager->getClient($instance);

		$count = min($count, 100);
		$page = max($page, 1);
		$response = $client->get('search', ['query' => $query, 'count' => $count, 'page' => $page]);
		return ResponseFormatter::searchResults($response, $query, $page, $count);
	}
}
