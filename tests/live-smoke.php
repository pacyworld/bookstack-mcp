#!/usr/bin/env php
<?php
/**
 * BookStack MCP Live Smoke Test
 *
 * Exercises the dual content type contract and the multipart image upload
 * fix against a REAL BookStack instance. Creates a test page and a test
 * image, verifies behavior, then deletes them both (fail-safe on error).
 *
 * Usage:
 *   LIVE_BOOKSTACK_INSTANCE=pacyworld php tests/live-smoke.php
 *
 * Requires BOOKSTACK_CONFIG (defaults to config/instances.json). Defaults
 * to the configured default instance. Destructive cleanup is idempotent.
 */

require dirname(__DIR__) . '/system/bootstrap.inc.php';

use BookStack\InstanceManager;

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

$config = getenv('BOOKSTACK_CONFIG') ?: dirname(__DIR__) . '/config/instances.json';
$manager = InstanceManager::fromFile($config);
$instance = getenv('LIVE_BOOKSTACK_INSTANCE') ?: $manager->getDefault();
if ($instance === '') {
	$names = array_keys($manager->listInstances());
	$instance = $names[0] ?? '';
}
if ($instance === '') {
	die("No instance configured in {$config}\n");
}
echo "Instance: {$instance}\n\n";

$client = $manager->getClient($instance);
$pageId = 0;
$imageId = 0;

try {
	// --- Create test page ---
	$page = $client->post('pages', [
		'name' => 'MCP Smoke Test (auto)',
		'book_id' => 0, // set below if needed
		'markdown' => "# MCP Smoke Test\n\nCreated by tests/live-smoke.php. Safe to delete.",
	]);
} catch (\Throwable $e) {
	// books > 0 pages need a book or chapter parent; try the first book
}

// pages_create needs a parent; find the first book
if (empty($page['id'])) {
	$books = $client->get('books', ['count' => 1, 'sort' => 'name']);
	$bookId = $books['data'][0]['id'] ?? 0;
	if (!$bookId) {
		die("No books on instance {$instance} to attach the test page to\n");
	}
	$page = $client->post('pages', [
		'name' => 'MCP Smoke Test (auto)',
		'book_id' => $bookId,
		'markdown' => "# MCP Smoke Test\n\nCreated by tests/live-smoke.php. Safe to delete.",
	]);
}
$pageId = (int) ($page['id'] ?? 0);
ok('pages_create: entity returned', $pageId > 0, json_encode($page));

// --- Read it back ---
$read = $client->get("pages/{$pageId}");
ok('pages_read: name matches', ($read['name'] ?? '') === 'MCP Smoke Test (auto)');
ok('pages_read: markdown present', str_contains($read['markdown'] ?? '', 'MCP Smoke Test'));

// --- Image upload (issue #3: real multipart) ---
$png = base64_decode(
	'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
);
$tmp = tempnam(sys_get_temp_dir(), 'bssmoke_');
file_put_contents($tmp, $png);
try {
	$image = $client->postMultipart('image-gallery', [
		'name' => 'mcp-smoke-test-' . date('Ymd'),
		'type' => 'gallery',
		'uploaded_to' => $pageId,
		'image' => new CURLFile($tmp, 'image/png', 'mcp-smoke.png'),
	]);
} finally {
	unlink($tmp);
}
$imageId = (int) ($image['id'] ?? 0);
ok('images_create: multipart upload succeeded', $imageId > 0,
	substr(json_encode($image), 0, 120));
ok('images_create: url returned', !empty($image['url']), $image['url'] ?? '(none)');
ok('images_create: uploaded_to matches', (int) ($image['uploaded_to'] ?? 0) === $pageId);

// --- Images list contains it ---
$images = $client->get('image-gallery', ['count' => 5, 'sort' => '-id']);
$found = false;
foreach ($images['data'] ?? [] as $img) {
	if ((int) ($img['id'] ?? 0) === $imageId) { $found = true; break; }
}
ok('images_list: new image visible', $found);

// --- Delete the test image ---
// The delete must either succeed (and actually remove the image) or fail
// LOUDLY with the HTTP status. A silent [] success is a regression (#2).
$imageDeleted = false;
try {
	$client->delete("image-gallery/{$imageId}");
	ok('images_delete: returned cleanly', true);
	$imageDeleted = true;
} catch (\RuntimeException $e) {
	ok('images_delete: fails loudly with HTTP status', str_contains($e->getMessage(), 'HTTP '), $e->getMessage());
}
if ($imageDeleted) {
	try {
		$client->get("image-gallery/{$imageId}");
		ok('images_delete: actually gone', false, 'image still readable');
	} catch (\RuntimeException $e) {
		ok('images_delete: actually gone', str_contains($e->getMessage(), '404') || str_contains($e->getMessage(), 'Not Found'), $e->getMessage());
	}
} else {
	// Image survives: delete was blocked before reaching BookStack
	echo "      (leftover image id {$imageId} remains — edge blocked DELETE)\n";
}

// --- Delete the test page (recycle bin) ---
try {
	$client->delete("pages/{$pageId}");
	try {
		$client->get("pages/{$pageId}");
		ok('pages_delete: page unreadable after delete', false, 'page still readable');
	} catch (\RuntimeException) {
		ok('pages_delete: page unreadable after delete', true);
	}

	$bin = $client->get('recycle-bin', ['count' => 10]);
	$inBin = false;
	foreach ($bin['data'] ?? [] as $item) {
		if ((int) ($item['deletable']['id'] ?? 0) === $pageId &&
		    str_contains($item['deletable_type'] ?? '', 'Page')) {
			$inBin = true;
			$binId = (int) $item['id'];
			break;
		}
	}
	ok('pages_delete: appears in recycle bin', $inBin);
	if ($inBin) {
		$client->delete("recycle-bin/{$binId}");
		ok('recyclebin_destroy: purged', true);
	}
} catch (\RuntimeException $e) {
	// Edge blocked the DELETE before BookStack saw it — the client must
	// fail loudly rather than report a silent [] success (#2).
	ok('pages_delete: fails loudly with HTTP status', str_contains($e->getMessage(), 'HTTP '), $e->getMessage());
	echo "      (leftover page id {$pageId} remains — edge blocked DELETE)\n";
}

echo "\nResults: {$pass} passed, {$fail} failed\n";
exit($fail > 0 ? 1 : 0);
