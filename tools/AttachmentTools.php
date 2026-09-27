<?php
/**
 * BookStack MCP Server — Attachment Tools
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

class AttachmentTools
{
	private InstanceManager $manager;

	public function __construct(InstanceManager $manager)
	{
		$this->manager = $manager;
	}

	#[McpTool(
		name: 'bookstack_attachments_list',
		description: 'List attachments.',
		readOnlyHint: true,
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'count' => ['type' => 'integer', 'description' => 'default 20, max 500'],
				'offset' => ['type' => 'integer'],
				'instance' => ['type' => 'string', 'description' => 'Required BookStack instance name (no default; see bookstack_list_instances)'],
			],
		]
	)]
	public function bookstack_attachments_list(int $count = 20, int $offset = 0, string $instance = ''): ToolResult
	{
		$client = $this->manager->getClient($instance);
		$response = $client->get('attachments', ['count' => min($count, 500), 'offset' => $offset]);
		return ToolResult::structured(ResponseFormatter::attachmentsList($response, $offset), $response);
	}

	#[McpTool(
		name: 'bookstack_attachments_read',
		description: 'Get an attachment, including its download URL.',
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
	public function bookstack_attachments_read(int $id, string $instance = ''): ToolResult
	{
		$client = $this->manager->getClient($instance);
		$response = $client->get("attachments/{$id}");
		return ToolResult::structured(ResponseFormatter::attachmentDetail($response), $response);
	}

	#[McpTool(
		name: 'bookstack_attachments_create',
		description: 'Attach an external URL link to a page.',
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'name' => ['type' => 'string'],
				'uploaded_to' => ['type' => 'integer', 'description' => 'Page ID'],
				'link' => ['type' => 'string', 'description' => 'External URL'],
				'instance' => ['type' => 'string', 'description' => 'Required BookStack instance name (no default; see bookstack_list_instances)'],
			],
			'required' => ['name', 'uploaded_to', 'link', 'instance'],
		]
	)]
	public function bookstack_attachments_create(string $name, int $uploaded_to, string $link, string $instance = ''): ToolResult
	{
		$client = $this->manager->getClient($instance);
		$response = $client->post('attachments', ['name' => $name, 'uploaded_to' => $uploaded_to, 'link' => $link]);
		return ToolResult::structured(
			ResponseFormatter::mutationSummary('Attachment created.', 'attachment', $response),
			$response
		);
	}

	#[McpTool(
		name: 'bookstack_attachments_update',
		description: 'Update an attachment\'s name or linked URL.',
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'id' => ['type' => 'integer'],
				'name' => ['type' => 'string'],
				'link' => ['type' => 'string'],
				'instance' => ['type' => 'string', 'description' => 'Required BookStack instance name (no default; see bookstack_list_instances)'],
			],
			'required' => ['id', 'instance'],
		]
	)]
	public function bookstack_attachments_update(int $id, string $name = '', string $link = '', string $instance = ''): ToolResult
	{
		$client = $this->manager->getClient($instance);
		$data = [];
		if (!empty($name)) $data['name'] = $name;
		if (!empty($link)) $data['link'] = $link;
		$response = $client->put("attachments/{$id}", $data);
		return ToolResult::structured(
			ResponseFormatter::mutationSummary('Attachment updated.', 'attachment', $response),
			$response
		);
	}

	#[McpTool(
		name: 'bookstack_attachments_delete',
		description: 'Permanently delete an attachment.',
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'id' => ['type' => 'integer'],
				'instance' => ['type' => 'string', 'description' => 'Required BookStack instance name (no default; see bookstack_list_instances)'],
			],
			'required' => ['id', 'instance'],
		]
	)]
	public function bookstack_attachments_delete(int $id, string $instance = ''): ToolResult
	{
		$client = $this->manager->getClient($instance);
		$client->delete("attachments/{$id}");
		return ToolResult::structured(
			ResponseFormatter::deleted('attachment', $id, false),
			['id' => $id, 'deleted' => true]
		);
	}
}
