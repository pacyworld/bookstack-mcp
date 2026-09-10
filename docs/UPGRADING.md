# UPGRADING — BookStack MCP Server

Developer/agent-facing. Read this **before** touching `libraries/`,
`bin/`, or any network I/O. Tool reference: `docs/TOOLS.md`.

## 1. The transport split (September 2026) changed the rules

Three independent libraries, vendored byte-identical from canonical
upstream **master** (pull first, then copy — never from another
consumer's tree):

| Vendored dir | Source | What it is |
|---|---|---|
| `libraries/EnchiladaMCP/` | `Enchilada/Extras` MCP/ | Protocol core. No I/O, no loop, no framework deps. |
| `libraries/Enchilada/Tortilla/` | `Enchilada/Tortilla` src/ | Wire transports, `EventLoop` port, `HttpClient`, `RequestEra`. |
| `libraries/EnchiladaHTTP/` | Extras HTTP/ | Blocking engine (legacy global class). **Frozen.** |
| `libraries/EnchiladaMultiHTTP/` | Extras HTTP/ | curl_multi engine (legacy global class). **Frozen.** |

(No Comal here → stdio runs its supported blocking mode; progress
notifications keep the host alive during long calls.)

## 2. Non-negotiables

1. **Eponymous vendoring, no guards.** Legacy global classes live in
   `libraries/<Class>/<Class>.class.php`. Never add
   `class_exists`/`require_once` guards: the framework autoloader is
   golden; a miss means wrong placement or namespace — fix that.
2. **Only `bin/bookstack-mcp` knows both sides.** Transports take
   primitives (`handler`, `progress` callables, version lists), never
   `McpServer`.
3. **The event loop is opt-in and app-owned; absent here.** The
   blocking regime is a supported configuration, not a fallback hack:
4. **`ping` is gone in MCP 2026-07-28** — `notifications/progress` is
   the only in-call liveness; bounded-poll waits must invoke the
   injected progress callable every slice.
5. **No blocking network I/O inside tools.** All BookStack API traffic
   goes through `BookStack\Client` → `Tortilla\HttpClient`. New
   endpoints extend the Client; nothing in `tools/` may instantiate raw
   HTTP.
   - `EnchiladaHTTP`/`EnchiladaMultiHTTP` are frozen: no MCP hooks, no
     behavior changes.
   - Should raw-socket I/O ever land here, use the
     `setTransport(?EventLoop, ?progress)` pattern (reference:
     mail-mcp `SocketImapClient`); writability waits must probe between
     parked slices (mail-mcp#27 review).

## 3. This server's wiring

- `bin/bookstack-mcp` composition root: `$loop =
  ComalEventLoop::create()` (null here) → `StdioTransport` primitives →
  `$manager->setHttpTransport($loop, $server->tick(...))` — without a
  loop the Client's `HttpClient` polls in place and emits progress each
  slice.
- **Releases**: `release.yml` stamps `APPLICATION_VERSION` from the tag
  — do not hand-bump; tag triggers CI phar + GitHub mirror.

## 4. Regression gates (before every commit)

- `phpunit` — green.
- Liveness suite:
  `php ~/Documents/Projects/engineering-docs/enchilada-extras/mcp-liveness-suite/transport-liveness.php --lib=libraries`
  (no Comal → blocking-regime assertions apply) — 9/9.
- Phar build + smoke (init/version/tools/ping/stderr/EOF).

## 5. Canonical references

- `engineering-docs/enchilada-extras/PLAN-TRANSPORT-SPLIT.md`
- `engineering-docs/enchilada-extras/DESIGN-RATIONALE-TRANSPORT-SPLIT.md`
- `engineering-docs/enchilada-extras/mcp-liveness-suite/README.md`
- `Enchilada/Extras` README (vendoring rules), `Enchilada/Tortilla` README
