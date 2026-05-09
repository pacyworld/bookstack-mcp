# BookStack MCP Server

A pure PHP Model Context Protocol server for managing multiple [BookStack](https://www.bookstackapp.com/) wiki instances via AI assistants.

## Features

- **58 tools** — books, pages, chapters, shelves, search, attachments, images, users, roles, permissions, audit log, recycle bin
- **Multi-instance** — manage multiple BookStack instances from a single server
- **Token authentication** — uses BookStack API token ID/secret pairs
- **Single PHAR binary** — no dependencies, no Composer, no Node.js
- Built on the [EnchiladaMCP](https://buenapp.org/docs/enchilada-mcp) library

## Installation (PHAR)

Download the latest PHAR from [Releases](https://pacyworld.dev/pacyworld/bookstack-mcp/releases):

```sh
curl -LO https://pacyworld.dev/pacyworld/bookstack-mcp/releases/latest/download/bookstack-mcp.phar
chmod +x bookstack-mcp.phar
```

Create a config file:

```sh
mkdir -p ~/.config/bookstack-mcp
cat > ~/.config/bookstack-mcp/instances.json << 'EOF'
{
    "default": "my-wiki",
    "instances": {
        "my-wiki": {
            "url": "https://wiki.example.com",
            "token_id": "YOUR_TOKEN_ID",
            "token_secret": "YOUR_TOKEN_SECRET"
        }
    }
}
EOF
```

Test:

```sh
php bookstack-mcp.phar
```

### From Source

```sh
git clone https://pacyworld.dev/pacyworld/bookstack-mcp.git
cd bookstack-mcp
cp config/instances.json.sample config/instances.json
# Edit config/instances.json with your BookStack URL and API tokens
php bin/bookstack-mcp
```

## AI Assistant Configuration

### Windsurf / Cascade

Add to your MCP config (`~/.codeium/windsurf/mcp_config.json`):

```json
{
    "bookstack": {
        "command": "php",
        "args": ["/path/to/bookstack-mcp.phar"]
    }
}
```

To use a config file in a non-default location:

```json
{
    "bookstack": {
        "command": "php",
        "args": ["/path/to/bookstack-mcp.phar", "--config=/path/to/instances.json"]
    }
}
```

### Claude Code

```json
{
    "mcpServers": {
        "bookstack": {
            "command": "php",
            "args": ["/path/to/bookstack-mcp.phar"]
        }
    }
}
```

## Configuration

Create a BookStack API token from your user profile: **User Menu → API Tokens → Create Token**.

```json
{
    "default": "my-wiki",
    "instances": {
        "my-wiki": {
            "url": "https://wiki.example.com",
            "token_id": "abc123",
            "token_secret": "xyz789...",
            "description": "My BookStack Wiki"
        },
        "other-wiki": {
            "url": "https://docs.other.com",
            "token_id": "def456",
            "token_secret": "uvw321...",
            "description": "Other Wiki"
        }
    }
}
```

Config file is searched in order:
1. `BOOKSTACK_CONFIG` environment variable
2. `--config=/path/to/instances.json` CLI argument
3. `~/.config/bookstack-mcp/instances.json`
4. `/usr/local/etc/bookstack-mcp/instances.json`

## Agent Skill

An [agent skill](docs/AGENT_SKILL.md) is included for AI assistants that support progressive skill discovery. Copy it to your skills directory or reference it directly.

## Requirements

- PHP 8.4+ with `curl` and `phar` extensions
- BookStack instance(s) with API access enabled

## License

BSD 2-Clause — see [LICENSE](LICENSE).
