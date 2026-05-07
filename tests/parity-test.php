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
        'cmd' => 'node /home/admin/Documents/Code/bookstack-mcp-server/dist/server.js',
        'env' => [
            'BOOKSTACK_BASE_URL' => 'https://docs.pacyworld.com/api',
            'BOOKSTACK_API_TOKEN' => 'dy2qXM6Ory5G0DqndKT9aVNOAkfYNPFB:PvIzIE1YuVBSW7c7EPeThNVwur6iCnO7',
            'MCP_TRANSPORT' => 'stdio',
            'LOG_LEVEL' => 'error',
            'NODE_ENV' => 'production',
        ],
    ],
    'php' => [
        'cmd' => 'php ' . escapeshellarg(dirname(__DIR__) . '/bin/bookstack-mcp'),
        'env' => [
            'BOOKSTACK_CONFIG' => dirname(__DIR__) . '/config/instances.json',
        ],
    ],
];

// Read-only tests safe to run on any instance
$tests = [
    ['bookstack_books_list',       ['count' => 2],              'books list'],
    ['bookstack_pages_list',       ['count' => 2],              'pages list'],
    ['bookstack_chapters_list',    ['count' => 2],              'chapters list'],
    ['bookstack_shelves_list',     ['count' => 2],              'shelves list'],
    ['bookstack_search',           ['query' => 'a', 'count' => 2], 'search'],
    ['bookstack_users_list',       ['count' => 2],              'users list'],
    ['bookstack_roles_list',       ['count' => 2],              'roles list'],
    ['bookstack_attachments_list', ['count' => 2],              'attachments list'],
    ['bookstack_images_list',      ['count' => 2],              'images list'],
    ['bookstack_recyclebin_list',  ['count' => 2],              'recycle bin'],
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

function structKeys($data, string $prefix = ''): array {
    if (!is_array($data)) return [];
    $keys = [];
    foreach ($data as $k => $v) {
        $path = $prefix ? "{$prefix}.{$k}" : (string)$k;
        $keys[] = $path;
        if (is_array($v) && !is_int($k)) {
            $keys = array_merge($keys, structKeys($v, $path));
        }
    }
    return $keys;
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
$pass = $fail = 0;
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
        // Compare response structure
        $nData = json_decode($nr['result']['content'][0]['text'] ?? '{}', true) ?? [];
        $pData = json_decode($pr['result']['content'][0]['text'] ?? '{}', true) ?? [];
        $nKeys = structKeys($nData);
        $pKeys = structKeys($pData);
        $keyDiff = array_diff($nKeys, $pKeys);
        if (!empty($keyDiff)) {
            echo "  (missing keys: " . implode(', ', array_slice($keyDiff, 0, 3)) . ")";
        }
        $pass++;
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
echo "Results: {$pass} pass, {$fail} regressions, " . count($missing) . " missing tools\n";
exit($fail > 0 ? 1 : 0);
