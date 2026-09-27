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
use EnchiladaMCP\ToolResult;
use BookStack\InstanceManager;
use BookStack\ResponseFormatter;

class ImageTools
{
	private InstanceManager $manager;

	public function __construct(InstanceManager $manager)
	{
		$this->manager = $manager;
	}

	#[McpTool(
		name: 'bookstack_images_list',
		description: 'List gallery images.',
		readOnlyHint: true,
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'count' => ['type' => 'integer', 'description' => 'default 20, max 500'],
				'offset' => ['type' => 'integer'],
				'sort' => ['type' => 'string', 'description' => 'name, created_at, updated_at'],
				'instance' => ['type' => 'string', 'description' => 'Required'],
			],
		]
	)]
	public function bookstack_images_list(int $count = 20, int $offset = 0, string $sort = 'created_at', string $instance = ''): ToolResult
	{
		$client = $this->manager->getClient($instance);
		$response = $client->get('image-gallery', ['count' => min($count, 500), 'offset' => $offset, 'sort' => $sort]);
		return ToolResult::structured(ResponseFormatter::imagesList($response, $offset, $sort), $response);
	}

	#[McpTool(
		name: 'bookstack_images_read',
		description: 'Get an image with its display and thumbnail URLs.',
		readOnlyHint: true,
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'id' => ['type' => 'integer'],
				'instance' => ['type' => 'string'],
			],
			'required' => ['id', 'instance'],
		]
	)]
	public function bookstack_images_read(int $id, string $instance = ''): ToolResult
	{
		$client = $this->manager->getClient($instance);
		$response = $client->get("image-gallery/{$id}");
		return ToolResult::structured(ResponseFormatter::imageDetail($response), $response);
	}

	#[McpTool(
		name: 'bookstack_images_create',
		description: 'Upload an image to the gallery.',
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'name' => ['type' => 'string'],
				'image' => ['type' => 'string', 'description' => 'Base64 image data'],
				'uploaded_to' => ['type' => 'integer', 'description' => 'Associated page ID'],
				'type' => ['type' => 'string', 'description' => 'gallery (default) or drawio'],
				'instance' => ['type' => 'string'],
			],
			'required' => ['name', 'image', 'instance'],
		]
	)]
	public function bookstack_images_create(string $name, string $image, int $uploaded_to = 0, string $type = 'gallery', string $instance = ''): ToolResult
	{
		$client = $this->manager->getClient($instance);

		// Accept an optional data-URI prefix (data:image/png;base64,...)
		if (str_starts_with($image, 'data:') && ($comma = strpos($image, ',')) !== false) {
			$image = substr($image, $comma + 1);
		}

		$binary = base64_decode($image, true);
		if ($binary === false) {
			throw new \InvalidArgumentException('image must be valid base64-encoded image content');
		}

		$mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($binary) ?: 'application/octet-stream';

		// BookStack's create-image endpoint expects a real multipart file
		// upload; posting the base64 string as a form field fails with
		// "Call to a member function getClientOriginalExtension() on string".
		// The route stays singular: POST /api/image-gallery is correct, the
		// plural /api/image-galleries route exists but is GET-only.
		$tmp = tempnam(sys_get_temp_dir(), 'bsimg_');
		file_put_contents($tmp, $binary);
		try {
			$fields = [
				'name' => $name,
				'image' => new \CURLFile($tmp, $mime, $name),
				'type' => $type,
			];
			if ($uploaded_to > 0) $fields['uploaded_to'] = $uploaded_to;
			$response = $client->postMultipart('image-gallery', $fields);
		} finally {
			unlink($tmp);
		}

		return ToolResult::structured(
			ResponseFormatter::mutationSummary('Image uploaded.', 'image', $response),
			$response
		);
	}

	#[McpTool(
		name: 'bookstack_images_update',
		description: 'Update an image\'s name.',
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'id' => ['type' => 'integer'],
				'name' => ['type' => 'string'],
				'instance' => ['type' => 'string'],
			],
			'required' => ['id', 'instance'],
		]
	)]
	public function bookstack_images_update(int $id, string $name = '', string $instance = ''): ToolResult
	{
		$client = $this->manager->getClient($instance);
		$data = [];
		if (!empty($name)) $data['name'] = $name;
		$response = $client->put("image-gallery/{$id}", $data);
		return ToolResult::structured(
			ResponseFormatter::mutationSummary('Image updated.', 'image', $response),
			$response
		);
	}

	#[McpTool(
		name: 'bookstack_images_delete',
		description: 'Permanently delete a gallery image.',
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'id' => ['type' => 'integer'],
				'instance' => ['type' => 'string'],
			],
			'required' => ['id', 'instance'],
		]
	)]
	public function bookstack_images_delete(int $id, string $instance = ''): ToolResult
	{
		$client = $this->manager->getClient($instance);
		$client->delete("image-gallery/{$id}");
		return ToolResult::structured(
			ResponseFormatter::deleted('image', $id, false),
			['id' => $id, 'deleted' => true]
		);
	}
}
