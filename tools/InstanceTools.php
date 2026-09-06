<?php
/**
 * BookStack MCP Server — Instance Management Tools
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

class InstanceTools
{
	private InstanceManager $manager;

	public function __construct(InstanceManager $manager)
	{
		$this->manager = $manager;
	}

	/**
	 * List all configured BookStack instances.
	 */
	#[McpTool(
		name: 'bookstack_list_instances',
		description: 'List all configured BookStack instances.',
		readOnlyHint: true,
		inputSchema: [
			'type' => 'object',
			'properties' => new \stdClass(),
		]
	)]
	public function bookstack_list_instances(): ToolResult
	{
		$instances = $this->manager->listInstances();
		return ToolResult::structured(
			ResponseFormatter::instanceList($instances),
			['instances' => $instances, 'count' => $this->manager->count()]
		);
	}
}
