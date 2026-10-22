# Changelog

## [Unreleased]

### Changed

- Re-vendored the Enchilada libraries in `libraries/` onto the
  EnchiladaCurl split: `EnchiladaCurl`, `EnchiladaMultiHTTP`, and
  `EnchiladaWorkerHTTP` now live in eponymous dirs as legacy globals on
  the abstract `EnchiladaCurl` base, vendored together with
  Tortilla and Comal from canonical upstream master. `EnchiladaHTTP`
  remains a standalone blocking client. The MCP library gains the
  prompt, completion, and elicitation classes plus `mcp.inc.php`.

### Added

- `tests/enchilada-curl/`: vendored curl behaviour suite plus the
  one-door-to-curl lint, run in CI on FreeBSD and Windows against
  `libraries/`.
