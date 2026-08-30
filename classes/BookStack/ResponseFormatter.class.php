<?php
/**
 * BookStack MCP Server — Response Formatter
 *
 * Converts raw BookStack API responses into compact, token-efficient
 * Markdown for LLM consumption. Strips noise fields (slugs, ownership
 * metadata, duplicate HTML representations, microsecond timestamps) and
 * adds explicit pagination footers so agents know when and how to fetch
 * more results.
 *
 * @package    BookstackMCP\BookStack
 * @author     Daniel Morante
 * @copyright  2026 The Daniel Morante Company, Inc.
 * @license    BSD-2-Clause
 */

namespace BookStack;

class ResponseFormatter
{
	/* ---------------- Content lists ---------------- */

	/**
	 * Format a books listing.
	 */
	public static function booksList(array $response, int $offset, string $sort): string
	{
		return self::listBlock('Books', $response, $offset,
			fn(array $b) => '- ' . self::idRef($b) . ' **' . $b['name'] . '**'
				. self::desc($b) . ' (updated ' . self::date($b['updated_at'] ?? null) . ')',
			'bookstack_books_list', "sort={$sort}"
		);
	}

	/**
	 * Format a pages listing.
	 */
	public static function pagesList(array $response, int $offset, string $sort): string
	{
		return self::listBlock('Pages', $response, $offset,
			fn(array $p) => '- ' . self::idRef($p) . ' **' . $p['name'] . '**'
				. (!empty($p['draft']) ? ' [draft]' : '')
				. ' — book_id ' . ($p['book_id'] ?? '?')
				. (!empty($p['chapter_id']) ? ', chapter_id ' . $p['chapter_id'] : '')
				. ' (updated ' . self::date($p['updated_at'] ?? null) . ')',
			'bookstack_pages_list', "sort={$sort}"
		);
	}

	/**
	 * Format a chapters listing.
	 */
	public static function chaptersList(array $response, int $offset, string $sort): string
	{
		return self::listBlock('Chapters', $response, $offset,
			fn(array $c) => '- ' . self::idRef($c) . ' **' . $c['name'] . '**'
				. ' — book_id ' . ($c['book_id'] ?? '?')
				. self::desc($c) . ' (updated ' . self::date($c['updated_at'] ?? null) . ')',
			'bookstack_chapters_list', "sort={$sort}"
		);
	}

	/**
	 * Format a shelves listing.
	 */
	public static function shelvesList(array $response, int $offset, string $sort): string
	{
		return self::listBlock('Shelves', $response, $offset,
			fn(array $s) => '- ' . self::idRef($s) . ' **' . $s['name'] . '**'
				. self::desc($s) . ' (updated ' . self::date($s['updated_at'] ?? null) . ')',
			'bookstack_shelves_list', "sort={$sort}"
		);
	}

	/* ---------------- Content details ---------------- */

	/**
	 * Format a book with its content hierarchy.
	 */
	public static function bookDetail(array $book): string
	{
		$out = self::detailHeader($book, 'book');
		$contents = $book['contents'] ?? [];
		if (!empty($contents)) {
			$lines = ['## Contents'];
			foreach ($contents as $item) {
				$type = $item['type'] ?? '';
				if ($type === 'chapter') {
					$lines[] = '- ' . $item['name'] . ' [chapter, id: ' . $item['id'] . ']';
					foreach ($item['pages'] ?? [] as $page) {
						$lines[] = '  - ' . $page['name'] . ' [page, id: ' . $page['id'] . ']'
							. (!empty($page['draft']) ? ' [draft]' : '');
					}
				} else {
					$lines[] = '- ' . $item['name'] . ' [page, id: ' . $item['id'] . ']'
						. (!empty($item['draft']) ? ' [draft]' : '');
				}
			}
			$out .= "\n\n" . implode("\n", $lines);
		} else {
			$out .= "\n\nThis book is empty.";
		}
		return $out;
	}

	/**
	 * Format a chapter with its pages.
	 */
	public static function chapterDetail(array $chapter): string
	{
		$out = self::detailHeader($chapter, 'chapter');
		$pages = $chapter['pages'] ?? [];
		if (!empty($pages)) {
			$lines = ['## Pages'];
			foreach ($pages as $page) {
				$lines[] = '- ' . $page['name'] . ' [page, id: ' . $page['id'] . ']'
					. (!empty($page['draft']) ? ' [draft]' : '');
			}
			$out .= "\n\n" . implode("\n", $lines);
		} else {
			$out .= "\n\nThis chapter has no pages.";
		}
		return $out;
	}

	/**
	 * Format a page with its content. Prefers Markdown; falls back to raw
	 * HTML when the page was authored in the WYSIWYG editor (no Markdown
	 * stored). The redundant second representation is never included —
	 * exports remain available via bookstack_pages_export.
	 */
	public static function pageDetail(array $page): string
	{
		$out = self::detailHeader($page, 'page');

		$markdown = trim($page['markdown'] ?? '');
		$html = trim($page['html'] ?? '');
		if ($markdown !== '') {
			$out .= "\n\n" . $markdown;
			if ($html !== '') {
				$out .= "\n\n---\nHTML version available: bookstack_pages_export id="
					. ($page['id'] ?? '?') . ' format=html';
			}
		} elseif ($html !== '') {
			$out .= "\n\n" . $html
				. "\n\n---\nThis page has no Markdown source (authored in HTML)."
				. ' Plain text available: bookstack_pages_export id=' . ($page['id'] ?? '?') . ' format=plaintext';
		} else {
			$out .= "\n\nThis page is empty.";
		}
		return $out;
	}

	/**
	 * Format a shelf with its books.
	 */
	public static function shelfDetail(array $shelf): string
	{
		$out = self::detailHeader($shelf, 'bookshelf');
		$books = $shelf['books'] ?? [];
		if (!empty($books)) {
			$lines = ['## Books'];
			foreach ($books as $book) {
				$lines[] = '- ' . self::idRef($book) . ' **' . $book['name'] . '**' . self::desc($book);
			}
			$out .= "\n\n" . implode("\n", $lines);
		} else {
			$out .= "\n\nThis shelf has no books.";
		}
		return $out;
	}

	/* ---------------- Search ---------------- */

	/**
	 * Format search results with cleaned snippets and page-based footer.
	 */
	public static function searchResults(array $response, string $query, int $page, int $count): string
	{
		$items = $response['data'] ?? [];
		$total = (int) ($response['total'] ?? 0);

		if (empty($items)) {
			return "# Search: \"{$query}\"\n\nNo results.";
		}

		$sections = ["# Search: \"{$query}\" — page {$page}, showing " . count($items) . " of {$total}"];
		foreach ($items as $item) {
			$lines = [];
			$type = $item['type'] ?? 'page';
			$lines[] = '## ' . $item['name'] . ' [' . $type . ', id: ' . $item['id'] . ']';

			// Breadcrumb context for pages/chapters
			$crumbs = [];
			if (!empty($item['book']['name'])) {
				$crumbs[] = $item['book']['name'] . ' [book, id: ' . $item['book']['id'] . ']';
			}
			if (!empty($item['chapter']['name'])) {
				$crumbs[] = $item['chapter']['name'] . ' [chapter, id: ' . $item['chapter']['id'] . ']';
			}
			$meta = [];
			if (!empty($crumbs)) {
				$meta[] = implode(' › ', $crumbs);
			}
			$meta[] = 'updated ' . self::date($item['updated_at'] ?? null);
			$lines[] = implode(' · ', $meta);

			$tags = self::tags($item['tags'] ?? []);
			if ($tags !== '') {
				$lines[] = 'Tags: ' . $tags;
			}

			$snippet = self::cleanSnippet($item['preview_html']['content'] ?? '');
			if ($snippet !== '') {
				$lines[] = '> ' . $snippet;
			}
			if (!empty($item['url'])) {
				$lines[] = $item['url'];
			}
			$sections[] = implode("\n", $lines);
		}

		$out = implode("\n\n", $sections);
		$shownThrough = ($page - 1) * $count + count($items);
		if ($shownThrough < $total) {
			$remaining = $total - $shownThrough;
			$out .= "\n\n---\nMore results: call bookstack_search with page=" . ($page + 1)
				. " ({$remaining} of {$total} remaining)";
		}
		return $out;
	}

	/* ---------------- Media ---------------- */

	/**
	 * Format an attachments listing.
	 */
	public static function attachmentsList(array $response, int $offset): string
	{
		return self::listBlock('Attachments', $response, $offset,
			fn(array $a) => '- ' . self::idRef($a) . ' **' . $a['name'] . '**'
				. (!empty($a['extension']) ? ' (.' . $a['extension'] . ')' : '')
				. ' — uploaded_to page ' . ($a['uploaded_to'] ?? '?')
				. (!empty($a['external']) ? ' [external link]' : '')
				. ' (updated ' . self::date($a['updated_at'] ?? null) . ')',
			'bookstack_attachments_list'
		);
	}

	/**
	 * Format an attachment detail.
	 */
	public static function attachmentDetail(array $attachment): string
	{
		$lines = [
			'# ' . $attachment['name'] . ' [attachment, id: ' . ($attachment['id'] ?? '?') . ']',
			'',
		];
		if (!empty($attachment['extension'])) {
			$lines[] = 'Extension: ' . $attachment['extension'];
		}
		$lines[] = 'Uploaded to page: ' . ($attachment['uploaded_to'] ?? '?');
		$lines[] = 'Type: ' . (!empty($attachment['external']) ? 'external link' : 'file');
		if (!empty($attachment['url'])) {
			$lines[] = 'URL: ' . $attachment['url'];
		}
		$links = $attachment['links'] ?? [];
		if (!empty($links['markdown'])) {
			$lines[] = 'Markdown embed: ' . $links['markdown'];
		}
		if (!empty($links['html'])) {
			$lines[] = 'HTML embed: ' . $links['html'];
		}
		$lines[] = 'Updated: ' . self::date($attachment['updated_at'] ?? null);
		return implode("\n", $lines);
	}

	/**
	 * Format an images listing.
	 */
	public static function imagesList(array $response, int $offset, string $sort): string
	{
		return self::listBlock('Images', $response, $offset,
			fn(array $i) => '- ' . self::idRef($i) . ' **' . $i['name'] . '**'
				. ' [' . ($i['type'] ?? 'gallery') . ']'
				. (!empty($i['uploaded_to']) ? ' — page ' . $i['uploaded_to'] : '')
				. (!empty($i['display_url']) ? ' — ' . $i['display_url'] : '')
				. ' (updated ' . self::date($i['updated_at'] ?? null) . ')',
			'bookstack_images_list', "sort={$sort}"
		);
	}

	/**
	 * Format an image detail.
	 */
	public static function imageDetail(array $image): string
	{
		$lines = [
			'# ' . $image['name'] . ' [image, id: ' . ($image['id'] ?? '?') . ']',
			'',
			'Type: ' . ($image['type'] ?? 'gallery'),
		];
		if (!empty($image['uploaded_to'])) {
			$lines[] = 'Associated page: ' . $image['uploaded_to'];
		}
		if (!empty($image['url'])) {
			$lines[] = 'URL: ' . $image['url'];
		}
		$thumbs = $image['thumbs'] ?? [];
		foreach (['display', 'gallery'] as $size) {
			if (!empty($thumbs[$size])) {
				$lines[] = ucfirst($size) . ' thumbnail: ' . $thumbs[$size];
			}
		}
		$lines[] = 'Updated: ' . self::date($image['updated_at'] ?? null);
		return implode("\n", $lines);
	}

	/* ---------------- Admin ---------------- */

	/**
	 * Format a users listing.
	 */
	public static function usersList(array $response, int $offset, string $sort): string
	{
		return self::listBlock('Users', $response, $offset,
			fn(array $u) => '- ' . self::idRef($u) . ' **' . $u['name'] . '**'
				. (!empty($u['email']) ? ' <' . $u['email'] . '>' : '')
				. (!empty($u['last_activity_at']) ? ' (last active ' . self::date($u['last_activity_at']) . ')' : ''),
			'bookstack_users_list', "sort={$sort}"
		);
	}

	/**
	 * Format a user detail with roles.
	 */
	public static function userDetail(array $user): string
	{
		$lines = [
			'# ' . $user['name'] . ' [user, id: ' . ($user['id'] ?? '?') . ']',
			'',
		];
		if (!empty($user['email'])) {
			$lines[] = 'Email: ' . $user['email'];
		}
		$roles = $user['roles'] ?? [];
		if (!empty($roles)) {
			$names = array_map(fn($r) => ($r['display_name'] ?? '?') . ' [id: ' . ($r['id'] ?? '?') . ']', $roles);
			$lines[] = 'Roles: ' . implode(', ', $names);
		}
		$lines[] = 'Created: ' . self::date($user['created_at'] ?? null);
		if (!empty($user['last_activity_at'])) {
			$lines[] = 'Last active: ' . self::date($user['last_activity_at']);
		}
		return implode("\n", $lines);
	}

	/**
	 * Format a roles listing.
	 */
	public static function rolesList(array $response, int $offset): string
	{
		return self::listBlock('Roles', $response, $offset,
			fn(array $r) => '- ' . self::idRef($r) . ' **' . $r['display_name'] . '**'
				. (!empty($r['description']) ? ' — ' . $r['description'] : '')
				. (!empty($r['mfa_enforced']) ? ' [MFA enforced]' : ''),
			'bookstack_roles_list'
		);
	}

	/**
	 * Format a role detail with users and permissions.
	 */
	public static function roleDetail(array $role): string
	{
		$lines = [
			'# ' . ($role['display_name'] ?? '?') . ' [role, id: ' . ($role['id'] ?? '?') . ']',
			'',
		];
		if (!empty($role['description'])) {
			$lines[] = $role['description'];
			$lines[] = '';
		}
		if (!empty($role['mfa_enforced'])) {
			$lines[] = 'MFA enforced: yes';
		}
		$users = $role['users'] ?? [];
		if (!empty($users)) {
			$names = array_map(fn($u) => ($u['name'] ?? '?') . ' [id: ' . ($u['id'] ?? '?') . ']', $users);
			$lines[] = 'Users (' . count($users) . '): ' . implode(', ', $names);
		}
		$permissions = $role['permissions'] ?? [];
		if (!empty($permissions)) {
			$lines[] = 'Permissions: ' . implode(', ', array_map(fn($p) => $p['name'] ?? (string) $p, $permissions));
		}
		$lines[] = 'Updated: ' . self::date($role['updated_at'] ?? null);
		return implode("\n", $lines);
	}

	/**
	 * Format the recycle bin listing.
	 */
	public static function recycleBinList(array $response, int $offset): string
	{
		return self::listBlock('Recycle Bin', $response, $offset,
			function (array $d) {
				$type = strtolower(basename(str_replace('\\', '/', $d['deletable_type'] ?? 'item')));
				$name = $d['deletable']['name'] ?? '(unknown)';
				$by = is_array($d['deleted_by'] ?? null)
					? ($d['deleted_by']['name'] ?? '?')
					: ('user ' . ($d['deleted_by'] ?? '?'));
				return '- [deletion_id: ' . $d['id'] . '] **' . $name . '** [' . $type . ']'
					. ' — deleted ' . self::date($d['created_at'] ?? null) . ' by ' . $by
					. ' · restore: bookstack_recyclebin_restore id=' . $d['id'];
			},
			'bookstack_recyclebin_list'
		);
	}

	/**
	 * Format the audit log.
	 */
	public static function auditLog(array $response, int $offset): string
	{
		return self::listBlock('Audit Log', $response, $offset,
			function (array $e) {
				$user = is_array($e['user'] ?? null) ? ($e['user']['name'] ?? '?') : ($e['user'] ?? '?');
				$line = '- ' . self::datetime($e['created_at'] ?? null) . ' · **' . ($e['type'] ?? '?') . '**';
				if (!empty($e['detail'])) {
					$line .= ' · ' . $e['detail'];
				}
				$line .= ' — by ' . $user;
				if (!empty($e['ip'])) {
					$line .= ' (' . $e['ip'] . ')';
				}
				return $line;
			},
			'bookstack_audit_log'
		);
	}

	/* ---------------- Shared helpers ---------------- */

	/**
	 * Format a detail header: title, description, tags, meta line.
	 */
	private static function detailHeader(array $entity, string $type): string
	{
		$lines = ['# ' . ($entity['name'] ?? $entity['display_name'] ?? '?')
			. ' [' . $type . ', id: ' . ($entity['id'] ?? '?') . ']'];

		if (!empty($entity['description'])) {
			$lines[] = '';
			$lines[] = $entity['description'];
		}

		$tags = self::tags($entity['tags'] ?? []);
		if ($tags !== '') {
			$lines[] = '';
			$lines[] = 'Tags: ' . $tags;
		}

		// Breadcrumb for pages and chapters
		$crumbs = [];
		if (!empty($entity['book']['name'])) {
			$crumbs[] = $entity['book']['name'] . ' [book, id: ' . $entity['book']['id'] . ']';
		}
		if (!empty($entity['chapter']['name'])) {
			$crumbs[] = $entity['chapter']['name'] . ' [chapter, id: ' . $entity['chapter']['id'] . ']';
		}

		$meta = [];
		if (!empty($crumbs)) {
			$meta[] = implode(' › ', $crumbs);
		}
		if (!empty($entity['draft'])) {
			$meta[] = 'DRAFT';
		}
		$meta[] = 'Created: ' . self::date($entity['created_at'] ?? null);
		$meta[] = 'Updated: ' . self::date($entity['updated_at'] ?? null);
		if (!empty($entity['url'])) {
			$meta[] = $entity['url'];
		}

		$lines[] = '';
		$lines[] = implode(' · ', $meta);
		return implode("\n", $lines);
	}

	/**
	 * Format a list response with header, item lines, and pagination footer.
	 *
	 * @param array    $response   Raw API response with data + total
	 * @param int      $offset     Requested offset
	 * @param callable $lineFn     Formats one item as a "- ..." line
	 * @param string   $tool       Tool name for the pagination hint
	 * @param string   $extraHint  Extra params to mention in the pagination hint
	 */
	private static function listBlock(string $title, array $response, int $offset, callable $lineFn, string $tool, string $extraHint = ''): string
	{
		$items = $response['data'] ?? [];
		$total = (int) ($response['total'] ?? 0);

		if (empty($items)) {
			return "# {$title}\n\nNo results.";
		}

		$lines = ["# {$title} — showing " . count($items) . " of {$total}", ''];
		foreach ($items as $item) {
			$lines[] = $lineFn($item);
		}

		$out = implode("\n", $lines);
		$nextOffset = $offset + count($items);
		if ($nextOffset < $total) {
			$remaining = $total - $nextOffset;
			$hint = "call {$tool} with offset={$nextOffset}";
			if ($extraHint !== '') {
				$hint .= ", {$extraHint}";
			}
			$out .= "\n\n---\nMore results: {$hint} ({$remaining} of {$total} remaining)";
		}
		return $out;
	}

	/**
	 * Short id reference: "[12]".
	 */
	private static function idRef(array $entity): string
	{
		return '[' . ($entity['id'] ?? '?') . ']';
	}

	/**
	 * Description suffix for list lines: " — description".
	 */
	private static function desc(array $entity): string
	{
		$desc = trim(preg_replace('/\s+/u', ' ', $entity['description'] ?? ''));
		if ($desc === '') {
			return '';
		}
		return ' — ' . mb_strimwidth($desc, 0, 120, '…');
	}

	/**
	 * Format tags: "name=value, name2".
	 */
	private static function tags(array $tags): string
	{
		if (empty($tags)) {
			return '';
		}
		$parts = [];
		foreach ($tags as $tag) {
			$parts[] = ($tag['name'] ?? '') . (isset($tag['value']) && $tag['value'] !== '' ? '=' . $tag['value'] : '');
		}
		return implode(', ', array_filter($parts));
	}

	/**
	 * Clean a search preview snippet: keep <strong> highlights as Markdown
	 * bold, strip other tags, decode entities, collapse whitespace.
	 */
	private static function cleanSnippet(string $html): string
	{
		$text = str_ireplace(['<strong>', '</strong>'], ['**', '**'], $html);
		$text = strip_tags($text);
		$text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
		return trim(preg_replace('/\s+/u', ' ', $text));
	}

	/**
	 * ISO timestamp → date only ("2026-05-31"). Microseconds and time are
	 * noise for content timestamps.
	 */
	private static function date(?string $iso): string
	{
		return $iso ? substr($iso, 0, 10) : '?';
	}

	/**
	 * ISO timestamp → date + time ("2026-05-31 19:07") for event logs.
	 */
	private static function datetime(?string $iso): string
	{
		return $iso ? str_replace('T', ' ', substr($iso, 0, 16)) : '?';
	}
}
