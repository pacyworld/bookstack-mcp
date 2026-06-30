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
		description: 'Search across all BookStack content. Supports advanced syntax: "exact phrase", {type:page|book|chapter|shelf}, {tag:name=value}, {created_by:me}. Page results contain snippets only — use bookstack_pages_read for full content.',
		readOnlyHint: true,
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'query' => ['type' => 'string', 'description' => 'Search query string with optional advanced syntax'],
				'count' => ['type' => 'integer', 'description' => 'Results per page (default 20, max 100)'],
				'page' => ['type' => 'integer', 'description' => 'Page number for pagination (default 1)'],
				'instance' => ['type' => 'string', 'description' => 'BookStack instance name (optional, uses default)'],
			],
			'required' => ['query'],
		]
	)]
	public function bookstack_search(string $query, int $count = 20, int $page = 1, string $instance = ''): array
	{
		$client = $this->manager->getClient($instance ?: null);

		$params = ['query' => $query, 'count' => min($count, 100), 'page' => max($page, 1)];
		return $client->get('search', $params);
	}
}
