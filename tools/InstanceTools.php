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
use BookStack\InstanceManager;

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
		description: 'List all configured BookStack instances. Shows which instance is the current default.',
		readOnlyHint: true,
		inputSchema: [
			'type' => 'object',
			'properties' => new \stdClass(),
		]
	)]
	public function bookstack_list_instances(): array
	{
		return [
			'default' => $this->manager->getDefault(),
			'instances' => $this->manager->listInstances(),
			'count' => $this->manager->count(),
		];
	}

	/**
	 * Switch the active default BookStack instance.
	 */
	#[McpTool(
		name: 'bookstack_switch_instance',
		description: 'Switch the active default BookStack instance. All subsequent tool calls without an explicit instance parameter will use this instance.',
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'instance' => ['type' => 'string', 'description' => 'Name of the instance to set as default (from bookstack_list_instances)'],
			],
			'required' => ['instance'],
		]
	)]
	public function bookstack_switch_instance(string $instance): array
	{
		$previous = $this->manager->getDefault();
		$this->manager->setDefault($instance);

		return [
			'success' => true,
			'previous_default' => $previous,
			'current_default' => $instance,
		];
	}
}
