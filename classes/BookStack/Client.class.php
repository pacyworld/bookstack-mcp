<?php
/**
 * BookStack MCP Server — API Client
 *
 * Thin HTTP client wrapping the BookStack REST API.
 * Uses Enchilada\Tortilla\HttpClient (loop-aware facade over
 * EnchiladaMultiHTTP) for requests with token authentication. When an
 * EventLoop is injected and the caller runs inside a transport dispatch
 * fiber, API waits park the fiber instead of blocking the server;
 * otherwise the client's poll loop keeps progress notifications flowing
 * during long waits (Windows stdio).
 *
 * @package    BookstackMCP\BookStack
 * @author     Daniel Morante
 * @copyright  2026 The Daniel Morante Company, Inc.
 * @license    BSD-2-Clause
 */

namespace BookStack;

// EnchiladaMultiHTTP lives in the HTTP/ library directory but the
// class name matches no vendored file or directory name, so the
// framework autoloader's guess patterns miss it and spl_autoload
// lowercases on case-sensitive filesystems.
if (!class_exists('EnchiladaMultiHTTP', false)) {
	require_once dirname(__DIR__, 2) . '/libraries/HTTP/EnchiladaMultiHTTP.class.php';
}

class Client
{
	/** @var \Enchilada\Tortilla\HttpClient Loop-aware HTTP transport */
	private \Enchilada\Tortilla\HttpClient $http;

	/** @var array Auth headers passed with every request */
	private array $authHeaders;

	/**
	 * Create a new BookStack API client.
	 *
	 * @param string                             $baseUrl     BookStack instance URL (no trailing slash)
	 * @param string                             $tokenId     API token ID
	 * @param string                             $tokenSecret API token secret
	 * @param int                                $timeout     Request timeout in seconds
	 * @param \Enchilada\Tortilla\EventLoop|null $loop        Event loop shared with the stdio transport;
	 *                                                        API waits park the dispatch fiber on it
	 * @param callable|null                      $progress    function(): void — emits a progress
	 *                                                        notification during blocking-mode API waits
	 */
	public function __construct(string $baseUrl, string $tokenId, string $tokenSecret, int $timeout = 30,
		?\Enchilada\Tortilla\EventLoop $loop = null, ?callable $progress = null)
	{
		$multi = new \EnchiladaMultiHTTP(rtrim($baseUrl, '/') . '/api');
		$multi->setTimeout($timeout);
		$this->http = new \Enchilada\Tortilla\HttpClient($multi, $loop, $progress);
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
	 * image-gallery). Values may be scalars or \CURLFile instances.
	 * EnchiladaMultiHTTP has no 'multipart' wire format, so the body is
	 * assembled here and sent raw with an explicit boundary header.
	 *
	 * @param  string $path   API path
	 * @param  array  $fields Multipart form fields
	 * @return array          Decoded JSON response
	 */
	public function postMultipart(string $path, array $fields): array
	{
		$boundary = 'McpBoundary' . uniqid();
		$body = '';
		foreach ($fields as $name => $value) {
			$body .= "--{$boundary}\r\n";
			if ($value instanceof \CURLFile) {
				$filename = $value->getPostFilename() !== '' ? $value->getPostFilename() : basename($value->getFilename());
				$body .= "Content-Disposition: form-data; name=\"{$name}\"; filename=\"{$filename}\"\r\n";
				if (($mime = $value->getMimeType()) !== '') {
					$body .= "Content-Type: {$mime}\r\n";
				}
				$body .= "\r\n" . file_get_contents($value->getFilename());
			} else {
				$body .= "Content-Disposition: form-data; name=\"{$name}\"\r\n\r\n" . (string) $value;
			}
			$body .= "\r\n";
		}
		$body .= "--{$boundary}--\r\n";

		$headers = array_merge($this->authHeaders, ["Content-Type: multipart/form-data; boundary={$boundary}"]);
		$result = $this->http->call($path, $body, 'POST', $headers, null, 'raw');
		if (!is_string($result) || $result === '') {
			return $this->finish(false, $path);
		}
		$decoded = json_decode($result, true);
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
	 * @param  mixed  $result Result from HttpClient::call()
	 * @param  string $path   API path (for error messages)
	 * @return array|string
	 * @throws \RuntimeException On failed requests
	 */
	private function finish(mixed $result, string $path): array|string
	{
		// null is the transport-failure slot in curl_multi outcomes
		// (EnchiladaHTTP surfaced the same case as false).
		if ($result === false || $result === null) {
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
		if ($response === false || $response === null) {
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
