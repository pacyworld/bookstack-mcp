#!/usr/bin/env php
<?php
/**
 * BookStack MCP ResponseFormatter Unit Test
 *
 * Verifies the Markdown output format of ResponseFormatter against
 * fixture data modeled on real BookStack API responses. No network
 * access required.
 *
 * Usage: php tests/formatter-test.php
 */

define('APPLICATION_ROOT', dirname(__DIR__) . DIRECTORY_SEPARATOR);
require APPLICATION_ROOT . 'classes' . DIRECTORY_SEPARATOR . 'BookStack'
	. DIRECTORY_SEPARATOR . 'ResponseFormatter.class.php';

use BookStack\ResponseFormatter;

$pass = 0;
$fail = 0;

function check(string $label, string $haystack, string $needle): void
{
	global $pass, $fail;
	if (str_contains($haystack, $needle)) {
		echo "PASS  {$label}\n";
		$pass++;
	} else {
		echo "FAIL  {$label} — expected to find: {$needle}\n";
		$fail++;
	}
}

function checkNot(string $label, string $haystack, string $needle): void
{
	global $pass, $fail;
	if (!str_contains($haystack, $needle)) {
		echo "PASS  {$label}\n";
		$pass++;
	} else {
		echo "FAIL  {$label} — expected NOT to find: {$needle}\n";
		$fail++;
	}
}

// --- Fixtures ---

$bookListItem = [
	'id' => 12,
	'slug' => 'securemessage-console',
	'name' => 'SecureMessage Console',
	'description' => "AWS-style unified management console.\nSSR + BFF architecture.",
	'created_at' => '2026-05-31T19:07:11.000000Z',
	'updated_at' => '2026-05-31T19:07:11.000000Z',
	'image_id' => null,
	'owned_by' => 3,
	'created_by' => 3,
	'updated_by' => 3,
	'cover' => null,
];

$searchItem = [
	'id' => 66,
	'name' => 'securearc reaches 171/171 on the ValiMail ARC suite',
	'slug' => 'securearc-reaches-171171',
	'book_id' => 34,
	'chapter_id' => 37,
	'draft' => false,
	'created_at' => '2026-07-29T15:41:20.000000Z',
	'updated_at' => '2026-07-29T15:41:20.000000Z',
	'url' => 'https://docs.example.com/books/securemilter/page/securearc-reaches-171171',
	'type' => 'page',
	'tags' => [['name' => 'suite', 'value' => 'valimail', 'order' => 0]],
	'book' => ['id' => 34, 'name' => 'SecureMilter', 'slug' => 'securemilter'],
	'chapter' => ['id' => 37, 'name' => 'Remediation Log', 'slug' => 'remediation-log'],
	'preview_html' => [
		'name' => 'secure<strong>arc</strong> reaches 171/171',
		'content' => " ...emilter-crypto \nsecure<strong>arc</strong> \n5028fbf &amp; more...",
	],
];

$page = [
	'id' => 66,
	'name' => 'Test Page',
	'slug' => 'test-page',
	'book_id' => 34,
	'chapter_id' => 37,
	'draft' => false,
	'template' => false,
	'created_at' => '2026-07-29T15:41:20.000000Z',
	'updated_at' => '2026-07-29T15:41:20.000000Z',
	'created_by' => ['id' => 3, 'name' => 'Cascade', 'slug' => 'cascade'],
	'updated_by' => ['id' => 3, 'name' => 'Cascade', 'slug' => 'cascade'],
	'owned_by' => ['id' => 3, 'name' => 'Cascade', 'slug' => 'cascade'],
	'book' => ['id' => 34, 'name' => 'SecureMilter', 'slug' => 'securemilter'],
	'chapter' => ['id' => 37, 'name' => 'Remediation Log', 'slug' => 'remediation-log'],
	'tags' => [],
	'html' => '<p>Hello <strong>world</strong></p>',
	'markdown' => "# Hello\n\n**world** content here.",
];

$htmlOnlyPage = array_merge($page, ['id' => 99, 'markdown' => '', 'html' => '<p>WYSIWYG body</p>']);

// --- books_list ---

$out = ResponseFormatter::booksList(['data' => [$bookListItem], 'total' => 6], 0, 'name');
check('books_list: header with counts', $out, '# Books — showing 1 of 6');
check('books_list: id + name', $out, '- [12] **SecureMessage Console**');
check('books_list: description whitespace collapsed', $out, 'AWS-style unified management console. SSR + BFF architecture.');
check('books_list: date trimmed', $out, '(updated 2026-05-31)');
checkNot('books_list: microsecond timestamp gone', $out, 'T19:07:11');
checkNot('books_list: no slug', $out, 'securemessage-console');
checkNot('books_list: no ownership fields', $out, 'owned_by');
check('books_list: pagination footer', $out, 'More results: call bookstack_books_list with offset=1, sort=name (5 of 6 remaining)');

$out = ResponseFormatter::booksList(['data' => [$bookListItem], 'total' => 1], 0, 'name');
checkNot('books_list: no footer when all shown', $out, 'More results');

$out = ResponseFormatter::booksList(['data' => [], 'total' => 0], 0, 'name');
check('books_list: empty list', $out, 'No results.');

// --- search ---

$out = ResponseFormatter::searchResults(['data' => [$searchItem], 'total' => 69], 'ARC', 2, 20);
check('search: header with page and counts', $out, '# Search: "ARC" — page 2, showing 1 of 69');
check('search: result header', $out, '## securearc reaches 171/171 on the ValiMail ARC suite [page, id: 66]');
check('search: breadcrumb', $out, 'SecureMilter [book, id: 34] › Remediation Log [chapter, id: 37]');
check('search: tags', $out, 'Tags: suite=valimail');
check('search: strong → markdown bold', $out, 'secure**arc**');
check('search: snippet whitespace collapsed', $out, '> ...emilter-crypto secure**arc** 5028fbf & more...');
check('search: url included', $out, 'https://docs.example.com/books/securemilter/page/securearc-reaches-171171');
checkNot('search: no raw html tags', $out, '<strong>');
checkNot('search: no preview_html field', $out, 'preview_html');
check('search: page footer with next page', $out, 'More results: call bookstack_search with page=3 (48 of 69 remaining)');

$out = ResponseFormatter::searchResults(['data' => [$searchItem], 'total' => 1], 'ARC', 1, 20);
checkNot('search: no footer when done', $out, 'More results');

$out = ResponseFormatter::searchResults(['data' => [], 'total' => 0], 'zzz', 1, 20);
check('search: empty', $out, 'No results.');

// --- pages_read ---

$out = ResponseFormatter::pageDetail($page);
check('pages_read: header', $out, '# Test Page [page, id: 66]');
check('pages_read: breadcrumb', $out, 'SecureMilter [book, id: 34] › Remediation Log [chapter, id: 37]');
check('pages_read: markdown body', $out, "**world** content here.");
checkNot('pages_read: html body dropped', $out, '<p>Hello');
check('pages_read: export hint', $out, 'HTML version available: bookstack_pages_export id=66 format=html');
checkNot('pages_read: no ownership noise', $out, 'Cascade');

$out = ResponseFormatter::pageDetail($htmlOnlyPage);
check('pages_read: html fallback shown', $out, '<p>WYSIWYG body</p>');
check('pages_read: html fallback noted', $out, 'no Markdown source');

$out = ResponseFormatter::pageDetail(array_merge($page, ['markdown' => '', 'html' => '']));
check('pages_read: empty page', $out, 'This page is empty.');

// --- books_read ---

$book = [
	'id' => 12,
	'name' => 'SecureMessage Console',
	'description' => 'AWS-style console.',
	'created_at' => '2026-05-31T19:07:11.000000Z',
	'updated_at' => '2026-05-31T19:07:11.000000Z',
	'contents' => [
		[
			'id' => 13, 'name' => 'Deployment & Operations', 'type' => 'chapter',
			'pages' => [
				['id' => 17, 'name' => 'Deployment & Upgrade Guide', 'draft' => false],
				['id' => 99, 'name' => 'WIP Notes', 'draft' => true],
			],
		],
		['id' => 50, 'name' => 'Loose Page', 'type' => 'page', 'draft' => false],
	],
	'tags' => [],
];
$out = ResponseFormatter::bookDetail($book);
check('books_read: header', $out, '# SecureMessage Console [book, id: 12]');
check('books_read: contents section separated', $out, "\n\n## Contents\n");
check('books_read: chapter line', $out, '- Deployment & Operations [chapter, id: 13]');
check('books_read: nested page line', $out, '  - Deployment & Upgrade Guide [page, id: 17]');
check('books_read: draft flag', $out, '  - WIP Notes [page, id: 99] [draft]');
check('books_read: direct page line', $out, '- Loose Page [page, id: 50]');

$out = ResponseFormatter::bookDetail(array_merge($book, ['contents' => []]));
check('books_read: empty book', $out, 'This book is empty.');

// --- chapters_read ---

$chapter = [
	'id' => 37,
	'name' => 'Remediation Log',
	'book_id' => 34,
	'created_at' => '2026-07-28T00:00:00.000000Z',
	'updated_at' => '2026-07-28T00:00:00.000000Z',
	'book' => ['id' => 34, 'name' => 'SecureMilter', 'slug' => 'securemilter'],
	'pages' => [['id' => 66, 'name' => 'Entry', 'draft' => false]],
];
$out = ResponseFormatter::chapterDetail($chapter);
check('chapters_read: header', $out, '# Remediation Log [chapter, id: 37]');
check('chapters_read: page line', $out, '- Entry [page, id: 66]');

// --- recyclebin_list ---

$recycleItem = [
	'id' => 7,
	'deleted_by' => ['id' => 3, 'name' => 'Cascade'],
	'created_at' => '2026-08-01T10:00:00.000000Z',
	'deletable_type' => 'BookStack\\Entities\\Models\\Page',
	'deletable' => ['id' => 42, 'name' => 'Old Page'],
];
$out = ResponseFormatter::recycleBinList(['data' => [$recycleItem], 'total' => 1], 0);
check('recyclebin: deletion id + name', $out, '[deletion_id: 7] **Old Page** [page]');
check('recyclebin: type shortened', $out, '[page] — deleted 2026-08-01 by Cascade');
check('recyclebin: restore hint', $out, 'bookstack_recyclebin_restore id=7');

// --- audit_log ---

$auditItem = [
	'id' => 100,
	'type' => 'page_update',
	'detail' => 'Test Page',
	'user' => ['id' => 3, 'name' => 'Cascade'],
	'ip' => '10.0.0.1',
	'created_at' => '2026-08-01T10:05:30.000000Z',
];
$out = ResponseFormatter::auditLog(['data' => [$auditItem], 'total' => 1], 0);
check('audit_log: entry line', $out, '- 2026-08-01 10:05 · **page_update** · Test Page — by Cascade (10.0.0.1)');

// --- users / roles ---

$user = [
	'id' => 3, 'name' => 'Cascade', 'email' => 'cascade@example.com',
	'created_at' => '2026-05-31T00:00:00.000000Z',
	'last_activity_at' => '2026-08-01T00:00:00.000000Z',
	'roles' => [['id' => 1, 'display_name' => 'Admin']],
];
$out = ResponseFormatter::userDetail($user);
check('users_read: header', $out, '# Cascade [user, id: 3]');
check('users_read: roles', $out, 'Roles: Admin [id: 1]');

$role = [
	'id' => 1, 'display_name' => 'Admin', 'description' => 'Full access',
	'mfa_enforced' => false,
	'created_at' => '2026-05-31T00:00:00.000000Z',
	'updated_at' => '2026-05-31T00:00:00.000000Z',
	'users' => [['id' => 3, 'name' => 'Cascade']],
	'permissions' => [['name' => 'content-create-all'], ['name' => 'settings-manage']],
];
$out = ResponseFormatter::roleDetail($role);
check('roles_read: header', $out, '# Admin [role, id: 1]');
check('roles_read: users', $out, 'Users (1): Cascade [id: 3]');
check('roles_read: permissions', $out, 'Permissions: content-create-all, settings-manage');

// --- attachments / images ---

$attachment = [
	'id' => 5, 'name' => 'diagram.png', 'extension' => 'png',
	'uploaded_to' => 66, 'external' => false, 'order' => 1,
	'created_at' => '2026-07-29T00:00:00.000000Z',
	'updated_at' => '2026-07-29T00:00:00.000000Z',
	'url' => 'https://docs.example.com/attachments/5',
	'links' => ['html' => '<a href="...">diagram.png</a>', 'markdown' => '[diagram.png](https://docs.example.com/attachments/5)'],
];
$out = ResponseFormatter::attachmentDetail($attachment);
check('attachments_read: header', $out, '# diagram.png [attachment, id: 5]');
check('attachments_read: markdown embed', $out, 'Markdown embed: [diagram.png](https://docs.example.com/attachments/5)');

$image = [
	'id' => 9, 'name' => 'screenshot', 'type' => 'gallery',
	'uploaded_to' => 66, 'url' => 'https://docs.example.com/uploads/images/gallery/2026-07/screenshot.png',
	'created_at' => '2026-07-29T00:00:00.000000Z',
	'updated_at' => '2026-07-29T00:00:00.000000Z',
	'thumbs' => ['gallery' => 'https://docs.example.com/thumb-g.png', 'display' => 'https://docs.example.com/thumb-d.png'],
];
$out = ResponseFormatter::imageDetail($image);
check('images_read: header', $out, '# screenshot [image, id: 9]');
check('images_read: thumbs', $out, 'Display thumbnail: https://docs.example.com/thumb-d.png');

// --- Summary ---

echo "\nResults: {$pass} passed, {$fail} failed\n";
exit($fail > 0 ? 1 : 0);
