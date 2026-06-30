<?php
/**
 * BookStack MCP Server — Image Tools
 *
 * @package    BookstackMCP\Tools
 * @author     Daniel Morante
 * @copyright  2026 The Daniel Morante Company, Inc.
 * @license    BSD-2-Clause
 */

use EnchiladaMCP\McpTool;
use BookStack\InstanceManager;

class ImageTools
{
	private InstanceManager $manager;

	public function __construct(InstanceManager $manager)
	{
		$this->manager = $manager;
	}

	#[McpTool(
		name: 'bookstack_images_list',
		description: 'List all images in the gallery.',
		readOnlyHint: true,
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'count' => ['type' => 'integer', 'description' => 'Number of images to return (default 20, max 500)'],
				'offset' => ['type' => 'integer', 'description' => 'Pagination offset'],
				'sort' => ['type' => 'string', 'description' => 'Sort field: name, created_at, updated_at'],
				'instance' => ['type' => 'string', 'description' => 'BookStack instance name (optional)'],
			],
		]
	)]
	public function bookstack_images_list(int $count = 20, int $offset = 0, string $sort = 'created_at', string $instance = ''): array
	{
		$client = $this->manager->getClient($instance ?: null);
		return $client->get('image-gallery', ['count' => min($count, 500), 'offset' => $offset, 'sort' => $sort]);
	}

	#[McpTool(
		name: 'bookstack_images_read',
		description: 'Get details of a specific image, including its display URL and thumbnail URLs.',
		readOnlyHint: true,
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'id' => ['type' => 'integer', 'description' => 'The unique ID of the image'],
				'instance' => ['type' => 'string', 'description' => 'BookStack instance name (optional)'],
			],
			'required' => ['id'],
		]
	)]
	public function bookstack_images_read(int $id, string $instance = ''): array
	{
		$client = $this->manager->getClient($instance ?: null);
		return $client->get("image-gallery/{$id}");
	}

	#[McpTool(
		name: 'bookstack_images_create',
		description: 'Upload a new image to the gallery. Provide base64-encoded image content.',
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'name' => ['type' => 'string', 'description' => 'Image title'],
				'image' => ['type' => 'string', 'description' => 'Base64 encoded image content'],
				'uploaded_to' => ['type' => 'integer', 'description' => 'Page ID this image is associated with'],
				'type' => ['type' => 'string', 'description' => 'Image type: gallery (default) or drawio'],
				'instance' => ['type' => 'string', 'description' => 'BookStack instance name (optional)'],
			],
			'required' => ['name', 'image'],
		]
	)]
	public function bookstack_images_create(string $name, string $image, int $uploaded_to = 0, string $type = 'gallery', string $instance = ''): array
	{
		$client = $this->manager->getClient($instance ?: null);
		$data = ['name' => $name, 'image' => $image, 'type' => $type];
		if ($uploaded_to > 0) $data['uploaded_to'] = $uploaded_to;
		return $client->post('image-gallery', $data);
	}

	#[McpTool(
		name: 'bookstack_images_update',
		description: 'Update an image\'s name.',
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'id' => ['type' => 'integer', 'description' => 'ID of the image to update'],
				'name' => ['type' => 'string', 'description' => 'New title'],
				'instance' => ['type' => 'string', 'description' => 'BookStack instance name (optional)'],
			],
			'required' => ['id'],
		]
	)]
	public function bookstack_images_update(int $id, string $name = '', string $instance = ''): array
	{
		$client = $this->manager->getClient($instance ?: null);
		$data = [];
		if (!empty($name)) $data['name'] = $name;
		return $client->put("image-gallery/{$id}", $data);
	}

	#[McpTool(
		name: 'bookstack_images_delete',
		description: 'Permanently delete an image from the gallery.',
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'id' => ['type' => 'integer', 'description' => 'ID of the image to delete'],
				'instance' => ['type' => 'string', 'description' => 'BookStack instance name (optional)'],
			],
			'required' => ['id'],
		]
	)]
	public function bookstack_images_delete(int $id, string $instance = ''): array
	{
		$client = $this->manager->getClient($instance ?: null);
		return $client->delete("image-gallery/{$id}");
	}
}
