<?php
/**
 * BookStack MCP Server — Recycle Bin Tools
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

class RecycleBinTools
{
	private InstanceManager $manager;

	public function __construct(InstanceManager $manager)
	{
		$this->manager = $manager;
	}

	#[McpTool(
		name: 'bookstack_recyclebin_list',
		description: 'List items currently in the recycle bin. Use to find deletion_id for restoration.',
		readOnlyHint: true,
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'count' => ['type' => 'integer', 'description' => 'Number of items to return (default 20, max 500)'],
				'offset' => ['type' => 'integer', 'description' => 'Pagination offset'],
				'instance' => ['type' => 'string', 'description' => 'BookStack instance name'],
			],
			'required' => ['instance'],
		]
	)]
	public function bookstack_recyclebin_list(int $count = 20, int $offset = 0, string $instance = ''): ToolResult
	{
		$client = $this->manager->getClient($instance);
		$response = $client->get('recycle-bin', ['count' => min($count, 500), 'offset' => $offset]);
		return ToolResult::structured(ResponseFormatter::recycleBinList($response, $offset), $response);
	}

	#[McpTool(
		name: 'bookstack_recyclebin_restore',
		description: 'Restore a deleted item from the recycle bin to its previous location.',
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'id' => ['type' => 'integer', 'description' => 'The deletion_id of the item to restore (from recyclebin_list, NOT the original entity ID)'],
				'instance' => ['type' => 'string', 'description' => 'BookStack instance name'],
			],
			'required' => ['id', 'instance'],
		]
	)]
	public function bookstack_recyclebin_restore(int $id, string $instance = ''): ToolResult
	{
		$client = $this->manager->getClient($instance);
		// BookStack returns 204 No Content on restore — the response is empty
		$client->put("recycle-bin/{$id}");
		return ToolResult::structured(
			"# Restored [deletion_id: {$id}]\n\nItem returned to its previous location.",
			['deletion_id' => $id, 'restored' => true]
		);
	}

	#[McpTool(
		name: 'bookstack_recyclebin_destroy',
		description: 'Permanently delete an item from the recycle bin. This is destructive and cannot be undone.',
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'id' => ['type' => 'integer', 'description' => 'The deletion_id of the item to permanently destroy'],
				'instance' => ['type' => 'string', 'description' => 'BookStack instance name'],
			],
			'required' => ['id', 'instance'],
		]
	)]
	public function bookstack_recyclebin_destroy(int $id, string $instance = ''): ToolResult
	{
		$client = $this->manager->getClient($instance);
		$client->delete("recycle-bin/{$id}");
		return ToolResult::structured(
			ResponseFormatter::deleted('deletion', $id, false),
			['deletion_id' => $id, 'destroyed' => true]
		);
	}
}
