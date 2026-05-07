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

	/** @var string Base URL (e.g., https://docs.example.com) */
	private string $baseUrl;

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
		$this->baseUrl = rtrim($baseUrl, '/');
		$this->http = new \EnchiladaHTTP($this->baseUrl);
		$this->http->setTimeout($timeout);
		$this->http->setCustomHeader('Authorization', "Token {$tokenId}:{$tokenSecret}");
	}

	/**
	 * GET request.
	 *
	 * @param  string $path   API path (e.g., /api/books)
	 * @param  array  $params Query parameters
	 * @return array           Decoded JSON response
	 */
	public function get(string $path, array $params = []): array
	{
		$url = $this->buildUrl($path, $params);
		$response = $this->http->get($url);
		return $this->handleResponse($response, $path);
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
		$url = $this->buildUrl($path);
		$response = $this->http->post($url, json_encode($data), 'application/json');
		return $this->handleResponse($response, $path);
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
		$url = $this->buildUrl($path);
		$response = $this->http->put($url, json_encode($data), 'application/json');
		return $this->handleResponse($response, $path);
	}

	/**
	 * DELETE request.
	 *
	 * @param  string $path API path
	 * @return array         Decoded JSON response (may be empty)
	 */
	public function delete(string $path): array
	{
		$url = $this->buildUrl($path);
		$response = $this->http->delete($url);
		$code = $this->http->getHttpCode();
		if ($code === 204) {
			return [];
		}
		return $this->handleResponse($response, $path);
	}

	/**
	 * Get the HTTP status code of the last request.
	 *
	 * @return int HTTP status code
	 */
	public function getLastHttpCode(): int
	{
		return $this->http->getHttpCode();
	}

	/**
	 * Build a full URL from path and optional query parameters.
	 *
	 * @param  string $path   API path
	 * @param  array  $params Query parameters
	 * @return string          Full URL
	 */
	private function buildUrl(string $path, array $params = []): string
	{
		$url = $this->baseUrl . '/api/' . ltrim($path, '/');
		if (!empty($params)) {
			$url .= '?' . http_build_query($params);
		}
		return $url;
	}

	/**
	 * Handle and decode an API response.
	 *
	 * @param  string|false $response Raw response body
	 * @param  string       $path     API path (for error messages)
	 * @return array                   Decoded JSON
	 * @throws \RuntimeException       On HTTP errors or invalid JSON
	 */
	private function handleResponse($response, string $path): array
	{
		$code = $this->http->getHttpCode();

		if ($response === false) {
			throw new \RuntimeException("BookStack API request failed: {$path} (no response)");
		}

		$decoded = json_decode($response, true);

		if ($code >= 400) {
			$message = $decoded['error']['message'] ?? $decoded['message'] ?? "HTTP {$code}";
			throw new \RuntimeException("BookStack API error ({$path}): {$message}", $code);
		}

		if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
			throw new \RuntimeException("BookStack API returned invalid JSON: {$path}");
		}

		return $decoded ?? [];
	}
}
