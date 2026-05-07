<?php
/**
 * BookStack MCP Server — API Client
 *
 * Thin HTTP client wrapping the BookStack REST API.
 * Uses EnchiladaHTTP for requests with token authentication.
 *
 * @package    BookstackMCP\BookStack
 * @author     Daniel Morante
 * @copyright  2026 The Daniel Morante Company, Inc.
 * @license    BSD-2-Clause
 */

namespace BookStack;

class Client
{
	/** @var \EnchiladaHTTP */
	private \EnchiladaHTTP $http;

	/** @var array Auth headers passed with every request */
	private array $authHeaders;

	/**
	 * Create a new BookStack API client.
	 *
	 * @param string $baseUrl     BookStack instance URL (no trailing slash)
	 * @param string $tokenId     API token ID
	 * @param string $tokenSecret API token secret
	 * @param int    $timeout     Request timeout in seconds
	 */
	public function __construct(string $baseUrl, string $tokenId, string $tokenSecret, int $timeout = 30)
	{
		$this->http = new \EnchiladaHTTP(rtrim($baseUrl, '/') . '/api');
		$this->http->setTimeout($timeout);
		$this->authHeaders = ["Authorization: Token {$tokenId}:{$tokenSecret}"];
	}

	/**
	 * GET request.
	 *
	 * @param  string $path   API path (e.g., books, pages/42)
	 * @param  array  $params Query parameters
	 * @return array|string   Decoded JSON response (or raw string for exports)
	 */
	public function get(string $path, array $params = []): array|string
	{
		$format = str_contains($path, '/export/') ? 'raw' : 'json';
		$result = $this->http->call($path, $params, 'GET', $this->authHeaders, null, $format);
		return $this->handleResponse($result, $path);
	}

	/**
	 * POST request.
	 *
	 * @param  string $path API path
	 * @param  array  $data Request body
	 * @return array         Decoded JSON response
	 */
	public function post(string $path, array $data = []): array
	{
		$result = $this->http->call($path, $data, 'POST', $this->authHeaders);
		return $this->handleResponse($result, $path);
	}

	/**
	 * PUT request.
	 *
	 * @param  string $path API path
	 * @param  array  $data Request body
	 * @return array         Decoded JSON response
	 */
	public function put(string $path, array $data = []): array
	{
		$result = $this->http->call($path, $data, 'PUT', $this->authHeaders);
		return $this->handleResponse($result, $path);
	}

	/**
	 * DELETE request.
	 *
	 * @param  string $path API path
	 * @return array         Decoded JSON response (may be empty)
	 */
	public function delete(string $path): array
	{
		$result = $this->http->call($path, null, 'DELETE', $this->authHeaders);
		if ($result === false) {
			return [];
		}
		return $this->handleResponse($result, $path);
	}

	/**
	 * Handle an API response.
	 *
	 * @param  mixed  $response Decoded JSON array, raw string, or false
	 * @param  string $path     API path (for error messages)
	 * @return array|string     Processed response
	 * @throws \RuntimeException On connection failure or API error
	 */
	private function handleResponse(mixed $response, string $path): array|string
	{
		if ($response === false) {
			throw new \RuntimeException("BookStack API request failed: {$path}");
		}

		// Raw string response (exports)
		if (is_string($response)) {
			return $response;
		}

		// Check for API error in response body
		if (isset($response['error']['message'])) {
			throw new \RuntimeException("BookStack API error ({$path}): " . $response['error']['message']);
		}

		return $response ?? [];
	}
}
