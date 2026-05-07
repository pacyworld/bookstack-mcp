#!/usr/bin/env php
<?php
/**
 * BookStack MCP Server — PHAR Builder
 *
 * Builds a self-contained .phar archive for distribution.
 * Usage: php bin/build-phar.php
 */

if (ini_get('phar.readonly')) {
    echo "Error: phar.readonly must be disabled. Run with:\n";
    echo "  php -d phar.readonly=0 bin/build-phar.php\n";
    exit(1);
}

$baseDir = dirname(__DIR__);
$pharFile = 'bookstack-mcp.phar';
$pharPath = $baseDir . '/' . $pharFile;

if (file_exists($pharPath)) {
    unlink($pharPath);
}

echo "Building {$pharFile}...\n";

$phar = new Phar($pharPath);
$phar->startBuffering();

// Add all source files
$dirs = ['system', 'classes', 'libraries', 'tools', 'includes'];
foreach ($dirs as $dir) {
    $fullDir = $baseDir . '/' . $dir;
    if (!is_dir($fullDir)) continue;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($fullDir, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if ($file->getExtension() === 'php' || $file->getExtension() === 'inc') {
            $localPath = $dir . '/' . $iterator->getSubPathname();
            $phar->addFile($file->getPathname(), $localPath);
        }
    }
}

// Add entry point
$phar->addFile($baseDir . '/bin/bookstack-mcp', 'bin/bookstack-mcp');

// Add config sample
$phar->addFile($baseDir . '/config/instances.json.sample', 'config/instances.json.sample');

// Discover tool classes for embedding in stub
$toolClasses = [];
foreach (glob($baseDir . '/tools/*.php') as $f) {
    $toolClasses[] = basename($f, '.php');
}
$toolListCode = "['".implode("','", $toolClasses)."']";

// Create stub
$stub = '#!/usr/bin/env php' . "\n" . '<?php' . "\n";
$stub .= <<<'STUB_PART1'
define('BOOKSTACK_MCP_PHAR', true);
Phar::mapPhar('bookstack-mcp.phar');
$pharRoot = 'phar://bookstack-mcp.phar/';
define('APPLICATION_LIBDIR', $pharRoot . 'libraries/');
require_once $pharRoot . 'system/app.conf.php';
require_once $pharRoot . 'system/autoload.inc.php';
if (defined('APPLICATION_DEBUG') && APPLICATION_DEBUG) { error_reporting(E_ALL); ini_set('display_errors', 1); }
date_default_timezone_set(defined('APPLICATION_TIMEZONE') ? APPLICATION_TIMEZONE : 'UTC');

use EnchiladaMCP\McpServer;
use EnchiladaMCP\StdioTransport;
use BookStack\InstanceManager;

$configPath = getenv('BOOKSTACK_CONFIG') ?: null;
foreach ($argv ?? [] as $arg) { if (str_starts_with($arg, '--config=')) $configPath = substr($arg, 9); }
if ($configPath === null) {
    $candidates = [getenv('HOME') . '/.config/bookstack-mcp/instances.json', '/usr/local/etc/bookstack-mcp/instances.json'];
    foreach ($candidates as $c) { if (file_exists($c)) { $configPath = $c; break; } }
}
if ($configPath === null || !file_exists($configPath)) {
    fwrite(STDERR, "[bookstack-mcp] ERROR: No config found. Set BOOKSTACK_CONFIG or use --config=\n");
    exit(1);
}
function debug(string $m): void { fwrite(STDERR, "[bookstack-mcp] " . $m . "\n"); }
try { $manager = InstanceManager::fromFile($configPath); } catch (\Exception $e) { fwrite(STDERR, "[bookstack-mcp] ERROR: " . $e->getMessage() . "\n"); exit(1); }
debug("Loaded " . $manager->count() . " instance(s) from {$configPath}");
$server = new McpServer(APPLICATION_SLUG, APPLICATION_VERSION);
$server->setInstructions('BookStack wiki management. Use bookstack_list_instances to see configured instances.');

STUB_PART1;

// Inject tool loading with build-time discovered class list
$stub .= '$toolClasses = ' . $toolListCode . ";\n";
$stub .= <<<'STUB_PART2'
foreach ($toolClasses as $cls) { require_once $pharRoot . 'tools/' . $cls . '.php'; $server->register(new $cls($manager)); }
$transport = new StdioTransport($server);
$transport->setLogger('debug');
$transport->run();
__HALT_COMPILER();
STUB_PART2;

$phar->setStub($stub);
$phar->stopBuffering();

// Make executable
chmod($pharPath, 0755);

$size = round(filesize($pharPath) / 1024, 1);
echo "Built: {$pharPath} ({$size} KB)\n";
echo "Test:  php {$pharFile} --config=/path/to/instances.json\n";
