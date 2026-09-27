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
use EnchiladaMCP\ToolResult;
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
		description: 'Search all content. Returns breadcrumbs and snippets only; read full pages with bookstack_pages_read.',
		readOnlyHint: true,
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'query' => ['type' => 'string', 'description' => 'Terms plus optional "exact phrase", {type:page|book|chapter|shelf}, {tag:name=value}, {created_by:me}'],
				'count' => ['type' => 'integer', 'description' => 'default 20, max 100'],
				'page' => ['type' => 'integer', 'description' => 'default 1'],
				'instance' => ['type' => 'string', 'description' => 'Required BookStack instance name (no default; see bookstack_list_instances)'],
			],
			'required' => ['query', 'instance'],
		]
	)]
	public function bookstack_search(string $query, int $count = 20, int $page = 1, string $instance = ''): ToolResult
	{
		$client = $this->manager->getClient($instance);

		$count = min($count, 100);
		$page = max($page, 1);
		$response = $client->get('search', ['query' => $query, 'count' => $count, 'page' => $page]);
		return ToolResult::structured(ResponseFormatter::searchResults($response, $query, $page, $count), $response);
	}
}
