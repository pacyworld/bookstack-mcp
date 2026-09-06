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
		return $this->finish($result, $path);
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
		return $this->finish($result, $path);
	}

	/**
	 * POST request with a real multipart/form-data body.
	 *
	 * For endpoints that expect an actual file upload (e.g. POST
	 * image-gallery). Values may be scalars or \CURLFile instances;
	 * cURL sets the Content-Type boundary automatically.
	 *
	 * @param  string $path   API path
	 * @param  array  $fields Multipart form fields
	 * @return array          Decoded JSON response
	 */
	public function postMultipart(string $path, array $fields): array
	{
		// EnchiladaHTTP returns the raw response body for non-JSON formats,
		// so decode the JSON body that BookStack still sends back.
		$result = $this->http->call($path, $fields, 'POST', $this->authHeaders, null, 'multipart');
		if ($result === false) {
			return $this->finish(false, $path);
		}
		$decoded = json_decode((string) $result, true);
		return $this->handleResponse($decoded ?? $result, $path);
	}

	/**
	 * DELETE request.
	 *
	 * BookStack answers a successful DELETE with 204 (empty body), which the
	 * HTTP layer surfaces as false — so false only means "empty or failed".
	 * Distinguish the two via the HTTP status code instead of silently
	 * returning [] for errors (a swallowed 403/404 looked like success).
	 *
	 * @param  string $path API path
	 * @return array         Decoded JSON response (may be empty)
	 */
	public function delete(string $path): array
	{
		$result = $this->http->call($path, null, 'DELETE', $this->authHeaders);
		return $this->finish($result, $path);
	}

	/**
	 * Treat a false (empty-body) result as success when the HTTP status is
	 * 2xx, otherwise fail loudly. BookStack answers successful mutations with
	 * 204 No Content, which the HTTP layer surfaces as false — but a failed
	 * request (403/404/5xx with an unreadable body) arrives the same way and
	 * must not masquerade as success.
	 *
	 * @param  mixed  $result Result from EnchiladaHTTP::call()
	 * @param  string $path   API path (for error messages)
	 * @return array|string
	 * @throws \RuntimeException On failed requests
	 */
	private function finish(mixed $result, string $path): array|string
	{
		if ($result === false) {
			$code = $this->http->getHttpCode();
			if ($code >= 200 && $code < 300) {
				return [];
			}
			throw new \RuntimeException(sprintf(
				'BookStack API request failed: %s (HTTP %d%s)',
				$path,
				$code,
				($curlError = $this->http->getLastCurlError()) !== '' ? '; ' . $curlError : ''
			));
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
			throw new \RuntimeException(sprintf(
				'BookStack API request failed: %s (HTTP %d%s)',
				$path,
				$this->http->getHttpCode(),
				($curlError = $this->http->getLastCurlError()) !== '' ? '; ' . $curlError : ''
			));
		}

		// HTTP-level error. BookStack error bodies are JSON, but an edge
		// (cache/WAF) may answer with plain HTML that never decoded — either
		// way, surface it instead of letting it masquerade as success.
		$code = $this->http->getHttpCode();
		if ($code >= 400) {
			$detail = '';
			if (is_array($response) && isset($response['error']['message'])) {
				$detail = ': ' . $response['error']['message'];
			} elseif (is_string($response) && trim($response) !== '') {
				$detail = ': ' . mb_strimwidth(trim(preg_replace('/\s+/', ' ', strip_tags($response))), 0, 200, '…');
			}
			throw new \RuntimeException("BookStack API error ({$path}): HTTP {$code}{$detail}");
		}

		// Raw string response (exports)
		if (is_string($response)) {
			return $response;
		}

		return $response ?? [];
	}
}
