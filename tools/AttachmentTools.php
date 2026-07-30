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
use BookStack\InstanceManager;

class AttachmentTools
{
	private InstanceManager $manager;

	public function __construct(InstanceManager $manager)
	{
		$this->manager = $manager;
	}

	#[McpTool(
		name: 'bookstack_attachments_list',
		description: 'List all attachments visible to the authenticated user.',
		readOnlyHint: true,
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'count' => ['type' => 'integer', 'description' => 'Number of attachments to return (default 20, max 500)'],
				'offset' => ['type' => 'integer', 'description' => 'Pagination offset'],
				'instance' => ['type' => 'string', 'description' => 'BookStack instance name'],
			],
		]
	)]
	public function bookstack_attachments_list(int $count = 20, int $offset = 0, string $instance = ''): array
	{
		$client = $this->manager->getClient($instance);
		return $client->get('attachments', ['count' => min($count, 500), 'offset' => $offset]);
	}

	#[McpTool(
		name: 'bookstack_attachments_read',
		description: 'Get details of a specific attachment, including its download URL.',
		readOnlyHint: true,
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'id' => ['type' => 'integer', 'description' => 'The unique ID of the attachment'],
				'instance' => ['type' => 'string', 'description' => 'BookStack instance name'],
			],
			'required' => ['id', 'instance'],
		]
	)]
	public function bookstack_attachments_read(int $id, string $instance = ''): array
	{
		$client = $this->manager->getClient($instance);
		return $client->get("attachments/{$id}");
	}

	#[McpTool(
		name: 'bookstack_attachments_create',
		description: 'Create a new attachment by linking to an external URL. Attach it to a page.',
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'name' => ['type' => 'string', 'description' => 'Name of the attachment'],
				'uploaded_to' => ['type' => 'integer', 'description' => 'ID of the page to attach to'],
				'link' => ['type' => 'string', 'description' => 'External URL to link'],
				'instance' => ['type' => 'string', 'description' => 'BookStack instance name'],
			],
			'required' => ['name', 'uploaded_to', 'link', 'instance'],
		]
	)]
	public function bookstack_attachments_create(string $name, int $uploaded_to, string $link, string $instance = ''): array
	{
		$client = $this->manager->getClient($instance);
		return $client->post('attachments', ['name' => $name, 'uploaded_to' => $uploaded_to, 'link' => $link]);
	}

	#[McpTool(
		name: 'bookstack_attachments_update',
		description: 'Update an attachment\'s name or linked URL.',
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'id' => ['type' => 'integer', 'description' => 'ID of the attachment to update'],
				'name' => ['type' => 'string', 'description' => 'New name'],
				'link' => ['type' => 'string', 'description' => 'New external URL'],
				'instance' => ['type' => 'string', 'description' => 'BookStack instance name'],
			],
			'required' => ['id', 'instance'],
		]
	)]
	public function bookstack_attachments_update(int $id, string $name = '', string $link = '', string $instance = ''): array
	{
		$client = $this->manager->getClient($instance);
		$data = [];
		if (!empty($name)) $data['name'] = $name;
		if (!empty($link)) $data['link'] = $link;
		return $client->put("attachments/{$id}", $data);
	}

	#[McpTool(
		name: 'bookstack_attachments_delete',
		description: 'Permanently delete an attachment.',
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'id' => ['type' => 'integer', 'description' => 'ID of the attachment to delete'],
				'instance' => ['type' => 'string', 'description' => 'BookStack instance name'],
			],
			'required' => ['id', 'instance'],
		]
	)]
	public function bookstack_attachments_delete(int $id, string $instance = ''): array
	{
		$client = $this->manager->getClient($instance);
		return $client->delete("attachments/{$id}");
	}
}
