#!/usr/bin/env php
<?php
/**
 * BookStack MCP Full Parity Test (CRUD + Admin)
 *
 * Tests write operations, user management, roles, permissions, and error handling.
 * DESTRUCTIVE: creates and deletes content on the target instance.
 *
 * Usage: php tests/parity-test-full.php
 * Prereq: Both servers configured to point at the same empty BookStack instance.
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

// --- Helpers ---

function startMcp(string $cmd, array $extraEnv): array {
    $env = array_merge(getenv(), $extraEnv);
    $desc = [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']];
    $proc = proc_open($cmd, $desc, $pipes, null, $env);
    if (!is_resource($proc)) die("Failed to start: {$cmd}\n");
    stream_set_blocking($pipes[1], false);
    return ['proc' => $proc, 'in' => $pipes[0], 'out' => $pipes[1], 'err' => $pipes[2]];
}

function rpc(array $s, int &$id, string $method, array $params = []): ?array {
    $id++;
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

function callTool(array $s, int &$id, string $tool, array $args = []): array {
    $r = rpc($s, $id, 'tools/call', ['name' => $tool, 'arguments' => $args]);
    $isError = isset($r['result']['isError']) && $r['result']['isError'];
    $text = $r['result']['content'][0]['text'] ?? '';
    $data = json_decode($text, true);
    return ['ok' => !$isError && $r !== null, 'data' => $data ?? $text, 'raw' => $text, 'isError' => $isError];
}

function stopMcp(array $s): void {
    fclose($s['in']); fclose($s['out']); fclose($s['err']);
    proc_terminate($s['proc']); proc_close($s['proc']);
}

function structKeys($data, string $prefix = '', int $depth = 0): array {
    if (!is_array($data) || $depth > 4) return [];
    $keys = [];
    foreach ($data as $k => $v) {
        if (is_int($k) && $k > 0) continue;
        $path = $prefix ? "{$prefix}.{$k}" : (string)$k;
        $keys[] = $path;
        if (is_array($v)) $keys = array_merge($keys, structKeys($v, $path, $depth + 1));
    }
    return $keys;
}

function compareResults(array $nodeResult, array $phpResult, string $label): array {
    $issues = [];

    // Both should succeed or both should fail
    if ($nodeResult['ok'] !== $phpResult['ok']) {
        $issues[] = "STATUS: node=" . ($nodeResult['ok'] ? 'ok' : 'fail') . " php=" . ($phpResult['ok'] ? 'ok' : 'fail');
        return $issues;
    }

    // Both errored — compare error presence (not exact message)
    if (!$nodeResult['ok'] && !$phpResult['ok']) {
        return []; // Both failed — that's consistent
    }

    // Both succeeded — compare structure
    if (is_array($nodeResult['data']) && is_array($phpResult['data'])) {
        $nKeys = structKeys($nodeResult['data']);
        $pKeys = structKeys($phpResult['data']);
        foreach (array_diff($nKeys, $pKeys) as $k) $issues[] = "MISSING: {$k}";
        foreach (array_diff($pKeys, $nKeys) as $k) $issues[] = "EXTRA: {$k}";

        // Type check top-level
        foreach ($nodeResult['data'] as $k => $v) {
            if (!array_key_exists($k, $phpResult['data'])) continue;
            if (gettype($v) !== gettype($phpResult['data'][$k])) {
                $issues[] = "TYPE .{$k}: node=" . gettype($v) . " php=" . gettype($phpResult['data'][$k]);
            }
        }
    }

    return $issues;
}

function report(string $label, array $issues, int &$pass, int &$structDiffs): void {
    if (empty($issues)) {
        echo str_pad($label, 35) . "\033[32mMATCH\033[0m\n";
        $pass++;
    } else {
        echo str_pad($label, 35) . "\033[31mDIFF\033[0m\n";
        foreach ($issues as $i) echo "      {$i}\n";
        $structDiffs++;
    }
}

// --- Main ---

echo "BookStack MCP Full CRUD Parity Test\n====================================\n\n";

$procs = [];
$id = 0;
foreach ($servers as $name => $cfg) {
    echo "Starting {$name}... ";
    $procs[$name] = startMcp($cfg['cmd'], $cfg['env']);
    $init = rpc($procs[$name], $id, 'initialize', [
        'protocolVersion' => '2025-03-26',
        'capabilities' => new stdClass(),
        'clientInfo' => ['name' => 'parity-full', 'version' => '1.0.0'],
    ]);
    if ($init && isset($init['result']['protocolVersion'])) {
        echo "OK\n";
        fwrite($procs[$name]['in'], json_encode(['jsonrpc'=>'2.0','method'=>'notifications/initialized']) . "\n");
    } else {
        echo "FAILED\n"; exit(1);
    }
}

$n = &$procs['nodejs'];
$p = &$procs['php'];
$nId = 100;
$pId = 200;
$pass = $structDiffs = $fail = 0;
$cleanup = ['books' => [], 'shelves' => [], 'roles' => [], 'users' => []];

echo "\n--- Books CRUD ---\n\n";

// Create
$nr = callTool($n, $nId, 'bookstack_books_create', ['name' => 'Node Test Book', 'description' => 'Created by Node.js']);
$pr = callTool($p, $pId, 'bookstack_books_create', ['name' => 'PHP Test Book', 'description' => 'Created by PHP']);
report('books_create', compareResults($nr, $pr, 'create'), $pass, $structDiffs);
$nBookId = $nr['data']['id'] ?? 0;
$pBookId = $pr['data']['id'] ?? 0;
$cleanup['books'][] = $nBookId;
$cleanup['books'][] = $pBookId;

// Read
$nr = callTool($n, $nId, 'bookstack_books_read', ['id' => $nBookId]);
$pr = callTool($p, $pId, 'bookstack_books_read', ['id' => $pBookId]);
report('books_read', compareResults($nr, $pr, 'read'), $pass, $structDiffs);

// Update
$nr = callTool($n, $nId, 'bookstack_books_update', ['id' => $nBookId, 'name' => 'Node Updated Book']);
$pr = callTool($p, $pId, 'bookstack_books_update', ['id' => $pBookId, 'name' => 'PHP Updated Book']);
report('books_update', compareResults($nr, $pr, 'update'), $pass, $structDiffs);

// Export markdown
$nr = callTool($n, $nId, 'bookstack_books_export', ['id' => $nBookId, 'format' => 'markdown']);
$pr = callTool($p, $pId, 'bookstack_books_export', ['id' => $pBookId, 'format' => 'markdown']);
// Exports return strings — both should succeed
$exportMatch = ($nr['ok'] === $pr['ok']);
report('books_export', $exportMatch ? [] : ['STATUS mismatch'], $pass, $structDiffs);

echo "\n--- Chapters CRUD ---\n\n";

$nr = callTool($n, $nId, 'bookstack_chapters_create', ['book_id' => $nBookId, 'name' => 'Node Chapter']);
$pr = callTool($p, $pId, 'bookstack_chapters_create', ['book_id' => $pBookId, 'name' => 'PHP Chapter']);
report('chapters_create', compareResults($nr, $pr, 'create'), $pass, $structDiffs);
$nChId = $nr['data']['id'] ?? 0;
$pChId = $pr['data']['id'] ?? 0;

$nr = callTool($n, $nId, 'bookstack_chapters_read', ['id' => $nChId]);
$pr = callTool($p, $pId, 'bookstack_chapters_read', ['id' => $pChId]);
report('chapters_read', compareResults($nr, $pr, 'read'), $pass, $structDiffs);

$nr = callTool($n, $nId, 'bookstack_chapters_update', ['id' => $nChId, 'name' => 'Node Ch Updated']);
$pr = callTool($p, $pId, 'bookstack_chapters_update', ['id' => $pChId, 'name' => 'PHP Ch Updated']);
report('chapters_update', compareResults($nr, $pr, 'update'), $pass, $structDiffs);

echo "\n--- Pages CRUD ---\n\n";

$nr = callTool($n, $nId, 'bookstack_pages_create', ['name' => 'Node Page', 'chapter_id' => $nChId, 'markdown' => "# Node\n\nHello from Node."]);
$pr = callTool($p, $pId, 'bookstack_pages_create', ['name' => 'PHP Page', 'chapter_id' => $pChId, 'markdown' => "# PHP\n\nHello from PHP."]);
report('pages_create', compareResults($nr, $pr, 'create'), $pass, $structDiffs);
$nPageId = $nr['data']['id'] ?? 0;
$pPageId = $pr['data']['id'] ?? 0;

$nr = callTool($n, $nId, 'bookstack_pages_read', ['id' => $nPageId]);
$pr = callTool($p, $pId, 'bookstack_pages_read', ['id' => $pPageId]);
report('pages_read', compareResults($nr, $pr, 'read'), $pass, $structDiffs);

$nr = callTool($n, $nId, 'bookstack_pages_update', ['id' => $nPageId, 'markdown' => "# Updated\n\nNew content."]);
$pr = callTool($p, $pId, 'bookstack_pages_update', ['id' => $pPageId, 'markdown' => "# Updated\n\nNew content."]);
report('pages_update', compareResults($nr, $pr, 'update'), $pass, $structDiffs);

$nr = callTool($n, $nId, 'bookstack_pages_export', ['id' => $nPageId, 'format' => 'markdown']);
$pr = callTool($p, $pId, 'bookstack_pages_export', ['id' => $pPageId, 'format' => 'markdown']);
report('pages_export', ($nr['ok'] === $pr['ok']) ? [] : ['STATUS mismatch'], $pass, $structDiffs);

echo "\n--- Shelves CRUD ---\n\n";

$nr = callTool($n, $nId, 'bookstack_shelves_create', ['name' => 'Node Shelf', 'books' => [$nBookId]]);
$pr = callTool($p, $pId, 'bookstack_shelves_create', ['name' => 'PHP Shelf', 'books' => [$pBookId]]);
report('shelves_create', compareResults($nr, $pr, 'create'), $pass, $structDiffs);
$nShelfId = $nr['data']['id'] ?? 0;
$pShelfId = $pr['data']['id'] ?? 0;
$cleanup['shelves'][] = $nShelfId;
$cleanup['shelves'][] = $pShelfId;

$nr = callTool($n, $nId, 'bookstack_shelves_read', ['id' => $nShelfId]);
$pr = callTool($p, $pId, 'bookstack_shelves_read', ['id' => $pShelfId]);
report('shelves_read', compareResults($nr, $pr, 'read'), $pass, $structDiffs);

$nr = callTool($n, $nId, 'bookstack_shelves_update', ['id' => $nShelfId, 'name' => 'Node Shelf Updated']);
$pr = callTool($p, $pId, 'bookstack_shelves_update', ['id' => $pShelfId, 'name' => 'PHP Shelf Updated']);
report('shelves_update', compareResults($nr, $pr, 'update'), $pass, $structDiffs);

echo "\n--- Search ---\n\n";

$nr = callTool($n, $nId, 'bookstack_search', ['query' => 'Updated', 'count' => 5]);
$pr = callTool($p, $pId, 'bookstack_search', ['query' => 'Updated', 'count' => 5]);
report('search', compareResults($nr, $pr, 'search'), $pass, $structDiffs);

echo "\n--- Users & Roles ---\n\n";

$nr = callTool($n, $nId, 'bookstack_roles_list', ['count' => 5]);
$pr = callTool($p, $pId, 'bookstack_roles_list', ['count' => 5]);
report('roles_list', compareResults($nr, $pr, 'list'), $pass, $structDiffs);

$nr = callTool($n, $nId, 'bookstack_roles_create', ['display_name' => 'Node Test Role']);
$pr = callTool($p, $pId, 'bookstack_roles_create', ['display_name' => 'PHP Test Role']);
report('roles_create', compareResults($nr, $pr, 'create'), $pass, $structDiffs);
$nRoleId = $nr['data']['id'] ?? 0;
$pRoleId = $pr['data']['id'] ?? 0;
$cleanup['roles'][] = $nRoleId;
$cleanup['roles'][] = $pRoleId;

$nr = callTool($n, $nId, 'bookstack_users_create', ['name' => 'Node User', 'email' => 'node-test@example.com', 'password' => 'TestPass123!', 'roles' => [$nRoleId]]);
$pr = callTool($p, $pId, 'bookstack_users_create', ['name' => 'PHP User', 'email' => 'php-test@example.com', 'password' => 'TestPass123!', 'roles' => [$pRoleId]]);
report('users_create', compareResults($nr, $pr, 'create'), $pass, $structDiffs);
$nUserId = $nr['data']['id'] ?? 0;
$pUserId = $pr['data']['id'] ?? 0;
$cleanup['users'][] = $nUserId;
$cleanup['users'][] = $pUserId;

$nr = callTool($n, $nId, 'bookstack_users_read', ['id' => $nUserId]);
$pr = callTool($p, $pId, 'bookstack_users_read', ['id' => $pUserId]);
report('users_read', compareResults($nr, $pr, 'read'), $pass, $structDiffs);

echo "\n--- Error Handling ---\n\n";

// Read non-existent resource
$nr = callTool($n, $nId, 'bookstack_pages_read', ['id' => 99999]);
$pr = callTool($p, $pId, 'bookstack_pages_read', ['id' => 99999]);
$errorMatch = ($nr['ok'] === $pr['ok']); // Both should fail
report('error: not found', $errorMatch ? [] : ["STATUS: node=" . ($nr['ok']?'ok':'fail') . " php=" . ($pr['ok']?'ok':'fail')], $pass, $structDiffs);

echo "\n--- Cleanup ---\n\n";

// Delete users
foreach ($cleanup['users'] as $uid) {
    if ($uid > 0) callTool($p, $pId, 'bookstack_users_delete', ['id' => $uid]);
}
echo "Deleted " . count($cleanup['users']) . " users\n";

// Delete roles
foreach ($cleanup['roles'] as $rid) {
    if ($rid > 0) callTool($p, $pId, 'bookstack_roles_delete', ['id' => $rid]);
}
echo "Deleted " . count($cleanup['roles']) . " roles\n";

// Delete shelves
foreach ($cleanup['shelves'] as $sid) {
    if ($sid > 0) callTool($p, $pId, 'bookstack_shelves_delete', ['id' => $sid]);
}
echo "Deleted " . count($cleanup['shelves']) . " shelves\n";

// Delete books (cascades chapters + pages)
foreach ($cleanup['books'] as $bid) {
    if ($bid > 0) callTool($p, $pId, 'bookstack_books_delete', ['id' => $bid]);
}
echo "Deleted " . count($cleanup['books']) . " books\n";

// Purge recycle bin
$bin = callTool($p, $pId, 'bookstack_recyclebin_list', ['count' => 50]);
if (is_array($bin['data']) && isset($bin['data']['data'])) {
    foreach ($bin['data']['data'] as $item) {
        callTool($p, $pId, 'bookstack_recyclebin_destroy', ['id' => $item['id']]);
    }
    echo "Purged " . count($bin['data']['data']) . " recycle bin items\n";
}

// Stop servers
foreach ($procs as $s) stopMcp($s);

echo "\n====================================\n";
echo "Results: {$pass} match, {$structDiffs} struct diffs, {$fail} regressions\n";
exit($structDiffs > 0 ? 1 : 0);
