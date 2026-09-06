#!/usr/bin/env php
<?php
/**
 * BookStack MCP Dual-Content Protocol Test
 *
 * Verifies the dual content type contract (MCP 2025-06-18):
 * every tool returns a Markdown text block in `content` AND a JSON payload
 * in `structuredContent`.
 *
 * Two layers:
 *   1. McpServer-level assertions with an in-process fake handler
 *      (structured/legacy/error paths, protocol version negotiation)
 *   2. Process-level assertions against bin/bookstack-mcp over stdio using
 *      the offline tools (server_info, list_instances, help) — no network
 *
 * Usage: php tests/structured-test.php
 */

require dirname(__DIR__) . '/system/bootstrap.inc.php';

use EnchiladaMCP\McpServer;
use EnchiladaMCP\McpTool;
use EnchiladaMCP\ToolResult;

$pass = 0;
$fail = 0;

function ok(string $label, bool $cond, string $detail = ''): void
{
	global $pass, $fail;
	if ($cond) {
		echo "PASS  {$label}\n";
		$pass++;
	} else {
		echo "FAIL  {$label}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
		$fail++;
	}
}

// --- Fake handler for in-process assertions ---

class FakeTools
{
	#[McpTool(name: 'fake_dual', description: 'Returns dual content')]
	public function fake_dual(): ToolResult
	{
		return ToolResult::structured("# Hello\n\nMarkdown body.", ['hello' => 1]);
	}

	#[McpTool(name: 'fake_legacy', description: 'Returns a plain array (pre-dual behavior)')]
	public function fake_legacy(): array
	{
		return ['legacy' => true];
	}
}

// --- Layer 1: McpServer-level ---

echo "--- McpServer (in-process) ---\n";

$server = new McpServer('test', '9.9.9');
$server->register(new FakeTools());

$init = $server->handleRequest([
	'jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize',
	'params' => ['protocolVersion' => '2025-03-26', 'capabilities' => [], 'clientInfo' => ['name' => 'test', 'version' => '1']],
]);
ok('initialize negotiates client protocol version',
	($init['result']['protocolVersion'] ?? null) === '2025-03-26',
	json_encode($init['result']['protocolVersion'] ?? null));

$dual = $server->handleRequest([
	'jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/call',
	'params' => ['name' => 'fake_dual', 'arguments' => []],
]);
ok('dual tool: text block present', ($dual['result']['content'][0]['text'] ?? '') === "# Hello\n\nMarkdown body.");
ok('dual tool: structuredContent present', ($dual['result']['structuredContent'] ?? null) === ['hello' => 1]);

$legacy = $server->handleRequest([
	'jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/call',
	'params' => ['name' => 'fake_legacy', 'arguments' => []],
]);
ok('legacy tool: text is JSON', ($legacy['result']['content'][0]['text'] ?? '') === '{"legacy":true}');
ok('legacy tool: no structuredContent', !array_key_exists('structuredContent', $legacy['result'] ?? []));

$unknown = $server->handleRequest([
	'jsonrpc' => '2.0', 'id' => 4, 'method' => 'tools/call',
	'params' => ['name' => 'fake_dul', 'arguments' => []],
]);
ok('unknown tool: isError result (not protocol error)', !empty($unknown['result']['isError']));
ok('unknown tool: message suggests closest match',
	str_contains($unknown['result']['content'][0]['text'] ?? '', 'fake_dual'),
	$unknown['result']['content'][0]['text'] ?? '');

// --- Layer 2: real server process over stdio (offline tools only) ---

echo "\n--- bin/bookstack-mcp (stdio, offline tools) ---\n";

$config = getenv('BOOKSTACK_CONFIG') ?: dirname(__DIR__) . '/config/instances.json';
$cmd = 'php ' . escapeshellarg(dirname(__DIR__) . '/bin/bookstack-mcp');
$env = array_merge(getenv(), ['BOOKSTACK_CONFIG' => $config]);
$proc = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env);
ok('server process started', is_resource($proc));
if (!is_resource($proc)) {
	exit(1);
}
stream_set_blocking($pipes[1], false);

$rpc = function (int $id, string $method, array $params = []) use (&$pipes): ?array {
	fwrite($pipes[0], json_encode(['jsonrpc' => '2.0', 'id' => $id, 'method' => $method, 'params' => $params]) . "\n");
	fflush($pipes[0]);
	$buf = '';
	$deadline = time() + 15;
	while (time() < $deadline) {
		$chunk = fread($pipes[1], 65536);
		if ($chunk) $buf .= $chunk;
		if (($pos = strpos($buf, "\n")) !== false) {
			$line = trim(substr($buf, 0, $pos));
			if ($line !== '') return json_decode($line, true);
		}
		usleep(50000);
	}
	return null;
};

$init = $rpc(0, 'initialize', [
	'protocolVersion' => '2025-03-26', 'capabilities' => new stdClass(),
	'clientInfo' => ['name' => 'structured-test', 'version' => '1.0.0'],
]);
ok('process initialize OK', isset($init['result']['protocolVersion']));
fwrite($pipes[0], json_encode(['jsonrpc' => '2.0', 'method' => 'notifications/initialized']) . "\n");

// Implementation metadata (MCP 2025-11-25)
$serverInfo = $init['result']['serverInfo'] ?? [];
ok('metadata: title', ($serverInfo['title'] ?? null) === APPLICATION_NAME, json_encode($serverInfo));
ok('metadata: description', !empty($serverInfo['description']));
ok('metadata: websiteUrl', ($serverInfo['websiteUrl'] ?? null) === APPLICATION_WEBSITE);
ok('metadata: icons', !empty($serverInfo['icons']) && str_ends_with($serverInfo['icons'][0]['src'] ?? '', 'docs/icon.svg'));

$offline = [
	'bookstack_server_info'      => [],
	'bookstack_list_instances'   => [],
	'bookstack_help'             => ['topic' => 'getting_started'],
	'bookstack_error_guide'      => ['error_code' => 'NOT_FOUND'],
	'bookstack_tool_categories'  => ['category' => 'pages'],
	'bookstack_usage_examples'   => ['workflow' => 'export_data'],
];

$id = 10;
foreach ($offline as $tool => $args) {
	$response = $rpc(++$id, 'tools/call', ['name' => $tool, 'arguments' => $args]);
	$result = $response['result'] ?? [];
	$text = $result['content'][0]['text'] ?? '';
	$structured = $result['structuredContent'] ?? null;
	ok("{$tool}: markdown content block", str_starts_with(ltrim($text), '#'), substr($text, 0, 40));
	ok("{$tool}: structuredContent payload", is_array($structured), gettype($structured));
}

fclose($pipes[0]);
fclose($pipes[1]);
fclose($pipes[2]);
proc_terminate($proc);
proc_close($proc);

echo "\nResults: {$pass} passed, {$fail} failed\n";
exit($fail > 0 ? 1 : 0);
