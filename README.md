# BookStack MCP Server

A pure PHP Model Context Protocol server for managing multiple [BookStack](https://www.bookstackapp.com/) wiki instances via AI assistants.

## Features

- **58 tools** — books, pages, chapters, shelves, search, attachments, images, users, roles, permissions, audit log, recycle bin
- **Multi-instance** — manage multiple BookStack instances from a single server
- **Token authentication** — uses BookStack API token ID/secret pairs
- **Single PHAR binary** — no dependencies, no Composer, no Node.js
- Built on the [EnchiladaMCP](https://buenapp.org/docs/enchilada-mcp) library

## Installation (PHAR — recommended)

```sh
# Download the latest PHAR
curl -LO https://pacyworld.dev/pacyworld/bookstack-mcp/releases/latest/download/bookstack-mcp.phar
chmod +x bookstack-mcp.phar

# Create configuration
mkdir -p ~/.config/bookstack-mcp
cat > ~/.config/bookstack-mcp/instances.json << 'EOF'
{
    "default": "my-wiki",
    "instances": {
        "my-wiki": {
            "url": "https://your-bookstack.example.com",
            "token_id": "YOUR_TOKEN_ID",
            "token_secret": "YOUR_TOKEN_SECRET",
            "description": "My BookStack Wiki"
        }
    }
}
EOF

# Test
echo '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{}}' | php bookstack-mcp.phar
```

## Installation (from source)

```sh
git clone https://pacyworld.dev/pacyworld/bookstack-mcp.git
cd bookstack-mcp
cp config/instances.json.sample config/instances.json
# Edit config/instances.json with your credentials
php bin/bookstack-mcp
```

## AI Assistant Configuration

### Windsurf / Cascade

Add to your MCP config (`~/.codeium/windsurf/mcp_config.json`):

```json
{
    "bookstack": {
        "command": "php",
        "args": ["/path/to/bookstack-mcp.phar"],
        "env": {
            "BOOKSTACK_CONFIG": "/home/you/.config/bookstack-mcp/instances.json"
        }
    }
}
```

### Claude Code

```json
{
    "mcpServers": {
        "bookstack": {
            "command": "php",
            "args": ["/path/to/bookstack-mcp.phar"],
            "env": {
                "BOOKSTACK_CONFIG": "/home/you/.config/bookstack-mcp/instances.json"
            }
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

## Requirements

- PHP 8.4+ (with curl extension)
- BookStack instance(s) with API access enabled

## License

BSD 2-Clause — see [LICENSE](LICENSE).
