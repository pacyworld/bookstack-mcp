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
		description: 'List recycle bin items with their deletion IDs (needed to restore or destroy).',
		readOnlyHint: true,
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'count' => ['type' => 'integer', 'description' => 'default 20, max 500'],
				'offset' => ['type' => 'integer'],
				'instance' => ['type' => 'string'],
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
		description: 'Restore a recycle bin item to its previous location.',
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'id' => ['type' => 'integer', 'description' => 'Deletion ID from the recycle bin list, NOT the entity ID'],
				'instance' => ['type' => 'string'],
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
		description: 'Permanently destroy a recycle bin item. Cannot be undone.',
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'id' => ['type' => 'integer', 'description' => 'Deletion ID, NOT the entity ID'],
				'instance' => ['type' => 'string'],
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
