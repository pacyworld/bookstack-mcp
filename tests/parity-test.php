#!/usr/bin/env php
<?php
/**
 * BookStack MCP Parity Test
 *
 * Spawns both Node.js and PHP MCP servers, sends identical JSON-RPC
 * requests to each, and compares:
 *   - Tool list coverage (missing/extra tools)
 *   - Response success/failure per tool
 *   - Response structure (key presence, not values)
 *
 * Usage: php tests/parity-test.php
 */

$servers = [
    'nodejs' => [
        'cmd' => getenv('PARITY_NODEJS_CMD') ?: 'node /home/admin/Documents/Code/bookstack-mcp-server/dist/server.js',
        'env' => [
            'BOOKSTACK_BASE_URL' => getenv('PARITY_BOOKSTACK_URL') ?: 'https://docs.pacyworld.com/api',
            'BOOKSTACK_API_TOKEN' => getenv('PARITY_BOOKSTACK_TOKEN') ?: die("Set PARITY_BOOKSTACK_TOKEN env var\n"),
            'MCP_TRANSPORT' => 'stdio',
            'LOG_LEVEL' => 'error',
            'NODE_ENV' => 'production',
        ],
    ],
    'php' => [
        'cmd' => 'php ' . escapeshellarg(dirname(__DIR__) . '/bin/bookstack-mcp'),
        'env' => [
            'BOOKSTACK_CONFIG' => getenv('PARITY_PHP_CONFIG') ?: dirname(__DIR__) . '/config/instances.json',
        ],
    ],
];

// Tests: [tool_name, arguments, description]
// Assumes test data exists (book ID 4, page ID 5 on pacyworld)
$tests = [
    // List operations
    ['bookstack_books_list',       ['count' => 2],                    'books list'],
    ['bookstack_pages_list',       ['count' => 2],                    'pages list'],
    ['bookstack_chapters_list',    ['count' => 2],                    'chapters list'],
    ['bookstack_shelves_list',     ['count' => 2],                    'shelves list'],
    ['bookstack_users_list',       ['count' => 2],                    'users list'],
    ['bookstack_roles_list',       ['count' => 2],                    'roles list'],
    ['bookstack_attachments_list', ['count' => 2],                    'attachments list'],
    ['bookstack_images_list',      ['count' => 2],                    'images list'],
    ['bookstack_recyclebin_list',  ['count' => 2],                    'recycle bin'],
    // Single read operations
    ['bookstack_books_read',       ['id' => 4],                       'books read'],
    ['bookstack_pages_read',       ['id' => 5],                       'pages read'],
    // Search
    ['bookstack_search',           ['query' => 'Hello', 'count' => 2],'search'],
    // System
    ['bookstack_audit_log',        ['count' => 2],                    'audit log'],
];

// --- Helpers ---

function startMcp(string $cmd, array $extraEnv): array {
    $env = array_merge(getenv(), $extraEnv);
    $desc = [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']];
    $proc = proc_open($cmd, $desc, $pipes, null, $env);
    if (!is_resource($proc)) die("Failed to start: {$cmd}\n");
    stream_set_blocking($pipes[1], false);
    return ['proc' => $proc, 'in' => $pipes[0], 'out' => $pipes[1], 'err' => $pipes[2]];
}

function rpc(array $s, int $id, string $method, array $params = []): ?array {
    $msg = json_encode(['jsonrpc'=>'2.0','id'=>$id,'method'=>$method,'params'=>$params]) . "\n";
    fwrite($s['in'], $msg);
    fflush($s['in']);
    $buf = '';
    $deadline = time() + 15;
    while (time() < $deadline) {
        $chunk = fread($s['out'], 65536);
        if ($chunk) $buf .= $chunk;
        if (($pos = strpos($buf, "\n")) !== false) {
            $line = trim(substr($buf, 0, $pos));
            if (!empty($line)) return json_decode($line, true);
        }
        usleep(50000);
    }
    return null;
}

function stopMcp(array $s): void {
    fclose($s['in']); fclose($s['out']); fclose($s['err']);
    proc_terminate($s['proc']); proc_close($s['proc']);
}

function structKeys($data, string $prefix = '', int $depth = 0): array {
    if (!is_array($data) || $depth > 4) return [];
    $keys = [];
    foreach ($data as $k => $v) {
        if (is_int($k) && $k > 0) continue; // Only inspect first array element
        $path = $prefix ? "{$prefix}.{$k}" : (string)$k;
        $keys[] = $path;
        if (is_array($v)) {
            $keys = array_merge($keys, structKeys($v, $path, $depth + 1));
        }
    }
    return $keys;
}

function deepCompare(array $nodeData, array $phpData): array {
    $issues = [];

    // Top-level key comparison
    $nodeKeys = structKeys($nodeData);
    $phpKeys = structKeys($phpData);

    $missingInPhp = array_diff($nodeKeys, $phpKeys);
    $extraInPhp = array_diff($phpKeys, $nodeKeys);

    foreach ($missingInPhp as $k) $issues[] = "MISSING: {$k}";
    foreach ($extraInPhp as $k) $issues[] = "EXTRA: {$k}";

    // Type comparison for shared top-level keys
    foreach ($nodeData as $k => $v) {
        if (!array_key_exists($k, $phpData)) continue;
        $nType = gettype($v);
        $pType = gettype($phpData[$k]);
        if ($nType !== $pType) {
            $issues[] = "TYPE: .{$k} (node={$nType}, php={$pType})";
        }
    }

    // For list responses, compare first item structure
    if (isset($nodeData['data'][0]) && isset($phpData['data'][0])) {
        $nItemKeys = structKeys($nodeData['data'][0], 'data[0]');
        $pItemKeys = structKeys($phpData['data'][0], 'data[0]');
        $itemMissing = array_diff($nItemKeys, $pItemKeys);
        $itemExtra = array_diff($pItemKeys, $nItemKeys);
        foreach ($itemMissing as $k) $issues[] = "ITEM MISSING: {$k}";
        foreach ($itemExtra as $k) $issues[] = "ITEM EXTRA: {$k}";
    }

    return $issues;
}

// --- Main ---

echo "BookStack MCP Parity Test\n=========================\n\n";

$procs = [];
foreach ($servers as $name => $cfg) {
    echo "Starting {$name}... ";
    $procs[$name] = startMcp($cfg['cmd'], $cfg['env']);
    $init = rpc($procs[$name], 0, 'initialize', [
        'protocolVersion' => '2025-03-26',
        'capabilities' => new stdClass(),
        'clientInfo' => ['name' => 'parity-test', 'version' => '1.0.0'],
    ]);
    if ($init && isset($init['result']['protocolVersion'])) {
        echo "OK\n";
        fwrite($procs[$name]['in'], json_encode(['jsonrpc'=>'2.0','method'=>'notifications/initialized']) . "\n");
    } else {
        echo "FAILED\n";
        stopMcp($procs[$name]);
        exit(1);
    }
}

// Compare tool lists
$nodeTools = rpc($procs['nodejs'], 1, 'tools/list');
$phpTools = rpc($procs['php'], 1, 'tools/list');
$nodeNames = array_column($nodeTools['result']['tools'] ?? [], 'name');
$phpNames = array_column($phpTools['result']['tools'] ?? [], 'name');
sort($nodeNames); sort($phpNames);

echo "\nNode.js: " . count($nodeNames) . " tools\n";
echo "PHP:     " . count($phpNames) . " tools\n";

$missing = array_diff($nodeNames, $phpNames);
$extra = array_diff($phpNames, $nodeNames);
if ($missing) { echo "\nMISSING in PHP:\n"; foreach ($missing as $t) echo "  - {$t}\n"; }
if ($extra) { echo "\nEXTRA in PHP:\n"; foreach ($extra as $t) echo "  + {$t}\n"; }

// Run tool call tests
echo "\n--- Tool Calls ---\n\n";
$pass = $fail = $structDiffs = 0;
$id = 10;

foreach ($tests as [$tool, $args, $label]) {
    $id++;
    $nr = rpc($procs['nodejs'], $id, 'tools/call', ['name' => $tool, 'arguments' => $args]);
    $pr = rpc($procs['php'], $id, 'tools/call', ['name' => $tool, 'arguments' => $args]);

    $nOk = $nr && isset($nr['result']['content']) && !isset($nr['result']['isError']);
    $pOk = $pr && isset($pr['result']['content']) && !isset($pr['result']['isError']);

    $status = match(true) {
        $nOk && $pOk => 'PASS',
        !$nOk && !$pOk => 'BOTH FAIL',
        $nOk && !$pOk => 'REGRESSION',
        default => 'PHP ONLY',
    };

    $icon = $status === 'PASS' ? "\033[32mPASS\033[0m" :
           ($status === 'REGRESSION' ? "\033[31mREGRESSION\033[0m" : "\033[33m{$status}\033[0m");

    echo str_pad($label, 22) . " {$icon}";

    if ($nOk && $pOk) {
        // Deep structural comparison. The PHP server intentionally returns
        // Markdown for read/list/search tools (v0.3.0+) instead of raw JSON;
        // non-JSON responses skip structural comparison — success/failure
        // parity is still enforced above.
        $nData = json_decode($nr['result']['content'][0]['text'] ?? '{}', true) ?? [];
        $pText = $pr['result']['content'][0]['text'] ?? '';
        $pData = json_decode($pText, true);
        if ($pData === null) {
            echo "  (markdown output, structure check skipped)";
            $pass++;
            echo "\n";
            continue;
        }
        $issues = deepCompare($nData, $pData);
        if (empty($issues)) {
            echo "  (structure match)";
            $pass++;
        } else {
            echo "\n";
            foreach ($issues as $issue) echo "      {$issue}\n";
            $structDiffs++;
        }
    } else {
        if (!$pOk && $pr) {
            $err = $pr['result']['content'][0]['text'] ?? $pr['error']['message'] ?? '?';
            echo "  PHP: " . substr($err, 0, 60);
        }
        if ($status === 'REGRESSION') $fail++;
    }
    echo "\n";
}

// Cleanup
foreach ($procs as $s) stopMcp($s);

echo "\n=========================\n";
echo "Results: {$pass} match, {$structDiffs} struct diffs, {$fail} regressions, " . count($missing) . " missing tools\n";
exit(($fail > 0 || $structDiffs > 0) ? 1 : 0);
